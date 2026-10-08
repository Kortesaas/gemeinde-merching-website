<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Backend login flow: credential check, brute-force throttling, the pending
 * second-factor state, session hardening and logout.
 */
class AdminAuthenticator
{
    /** Session keys */
    public const PENDING_USER_ID = 'admin.login.pending_user_id';

    public const PENDING_SINCE = 'admin.login.pending_since';

    public const AUTHENTICATED_AT = 'admin.authenticated_at';

    public const MFA_VERIFIED = 'admin.mfa_verified';

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Check e-mail + password. Throws a generic validation error on failure so
     * the response never reveals whether an account exists or is deactivated.
     *
     * @throws ValidationException
     */
    public function validateCredentials(Request $request, string $email, #[SensitiveParameter] string $password): User
    {
        $email = mb_strtolower(trim($email));
        $keys = $this->throttleKeys($request, $email);

        $this->ensureNotRateLimited($request, $keys);

        $user = (new Timebox)->call(function (Timebox $timebox) use ($email, $password): ?User {
            $user = User::query()->where('email', $email)->first();

            // Always run one hash verification so unknown accounts take as long as known ones.
            $passwordMatches = Hash::check($password, $user->password ?? $this->dummyHash());

            if ($user !== null && $passwordMatches && $user->is_active) {
                $timebox->returnEarly();

                return $user;
            }

            if ($user !== null) {
                $this->audit->record('auth.login_failed', $user, ['reason' => $passwordMatches ? 'inactive' : 'password'], actor: null);
            }

            return null;
        }, (int) config('auth.timebox_duration'));

        if ($user === null) {
            $decay = (int) config('admin.throttle.lockout_seconds');
            $this->limiter->hit($keys['account'], $decay);
            $this->limiter->hit($keys['client'], $decay);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $this->limiter->clear($keys['account']);

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        return $user;
    }

    /**
     * Password was correct; remember the user until the second factor is provided.
     */
    public function beginTwoFactorChallenge(Request $request, User $user): void
    {
        $request->session()->regenerate(destroy: true);
        $request->session()->put([
            self::PENDING_USER_ID => $user->getKey(),
            self::PENDING_SINCE => now()->getTimestamp(),
        ]);
    }

    /**
     * The user that passed the password step and still owes the second factor.
     */
    public function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING_USER_ID);
        $since = (int) $request->session()->get(self::PENDING_SINCE, 0);

        if ($id === null || now()->getTimestamp() - $since > (int) config('admin.mfa.challenge_timeout')) {
            $this->clearPendingChallenge($request);

            return null;
        }

        $user = User::query()->whereKey($id)->where('is_active', true)->first();

        return $user?->hasEnabledTwoFactor() ? $user : null;
    }

    public function clearPendingChallenge(Request $request): void
    {
        $request->session()->forget([self::PENDING_USER_ID, self::PENDING_SINCE]);
    }

    /**
     * Establish the authenticated session. A new session ID and CSRF token are
     * issued to prevent session fixation.
     */
    public function completeLogin(Request $request, User $user, bool $mfaVerified): void
    {
        $this->clearPendingChallenge($request);

        $this->guard()->login($user);

        $request->session()->regenerate(destroy: true);
        $request->session()->regenerateToken();
        $request->session()->put([
            self::AUTHENTICATED_AT => now()->getTimestamp(),
            self::MFA_VERIFIED => $mfaVerified,
        ]);

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->record('auth.login', $user, ['mfa' => $mfaVerified], actor: $user);
    }

    /**
     * Mark the current session as MFA-verified (after enrolment).
     */
    public function markTwoFactorVerified(Request $request): void
    {
        $request->session()->regenerate(destroy: true);
        $request->session()->put(self::MFA_VERIFIED, true);
    }

    public function logout(Request $request, string $reason = 'user'): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->audit->record('auth.logout', $user, ['reason' => $reason], actor: $user);
        }

        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * @throws ValidationException
     */
    public function ensureTwoFactorNotRateLimited(User $user, string $field): string
    {
        $key = 'admin-mfa:'.$user->getKey();

        if ($this->limiter->tooManyAttempts($key, (int) config('admin.throttle.mfa_attempts'))) {
            throw ValidationException::withMessages([
                $field => __('auth.throttle', ['seconds' => $this->limiter->availableIn($key)]),
            ]);
        }

        return $key;
    }

    public function hitTwoFactorLimiter(string $key): void
    {
        $this->limiter->hit($key, (int) config('admin.throttle.lockout_seconds'));
    }

    public function clearTwoFactorLimiter(string $key): void
    {
        $this->limiter->clear($key);
    }

    /**
     * Rate-limiter keys. The client IP is only used in hashed form inside the
     * short-lived cache entry and is never written to logs or the database.
     *
     * @return array{account: string, client: string}
     */
    private function throttleKeys(Request $request, string $email): array
    {
        $client = hash('sha256', (string) $request->ip());

        return [
            'account' => 'admin-login:'.hash('sha256', $email.'|'.$client),
            'client' => 'admin-login-client:'.$client,
        ];
    }

    /**
     * @param  array{account: string, client: string}  $keys
     *
     * @throws ValidationException
     */
    private function ensureNotRateLimited(Request $request, array $keys): void
    {
        $limits = [
            $keys['account'] => (int) config('admin.throttle.login_attempts'),
            $keys['client'] => (int) config('admin.throttle.login_attempts_per_client'),
        ];

        foreach ($limits as $key => $maxAttempts) {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                event(new Lockout($request));

                throw ValidationException::withMessages([
                    'email' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($key)]),
                ]);
            }
        }
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('timing-equalisation-only');
    }

    private function guard(): StatefulGuard
    {
        return Auth::guard('web');
    }
}
