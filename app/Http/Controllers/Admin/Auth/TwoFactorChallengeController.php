<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\AdminAuthenticator;
use App\Services\Auth\TwoFactorAuthenticator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Second login step: TOTP code or single-use recovery code.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly AdminAuthenticator $authenticator,
        private readonly TwoFactorAuthenticator $twoFactor,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->authenticator->pendingUser($request) === null) {
            return $this->restartLogin();
        }

        return view('admin.auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        return $this->verify($request, 'code', fn ($user) => $this->twoFactor->verifyCode($user, $data['code']));
    }

    public function storeRecoveryCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['recovery_code' => ['required', 'string', 'max:30']]);

        $response = $this->verify($request, 'recovery_code', function ($user) use ($data) {
            if (! $this->twoFactor->useRecoveryCode($user, $data['recovery_code'])) {
                return false;
            }

            $this->audit->record('auth.recovery_code_used', $user, [
                'remaining' => $this->twoFactor->remainingRecoveryCodes($user),
            ], actor: $user);

            return true;
        });

        return $response->with('status', 'Sie haben einen Wiederherstellungscode verwendet. '
            .'Erzeugen Sie bei Bedarf neue Codes unter „Zwei-Faktor-Authentisierung“.');
    }

    /**
     * @param  callable(User): bool  $check
     */
    private function verify(Request $request, string $field, callable $check): RedirectResponse
    {
        $user = $this->authenticator->pendingUser($request);

        if ($user === null) {
            return $this->restartLogin();
        }

        $throttleKey = $this->authenticator->ensureTwoFactorNotRateLimited($user, $field);

        if (! $check($user)) {
            $this->authenticator->hitTwoFactorLimiter($throttleKey);
            $this->audit->record('auth.mfa_failed', $user, ['method' => $field], actor: null);

            throw ValidationException::withMessages([
                $field => $field === 'code'
                    ? 'Der Code ist ungültig oder abgelaufen.'
                    : 'Der Wiederherstellungscode ist ungültig oder wurde bereits verwendet.',
            ]);
        }

        $this->authenticator->clearTwoFactorLimiter($throttleKey);
        $this->authenticator->completeLogin($request, $user, mfaVerified: true);

        return redirect()->intended(route('admin.dashboard'));
    }

    private function restartLogin(): RedirectResponse
    {
        return redirect()->route('admin.login')
            ->with('status', 'Bitte melden Sie sich erneut an.');
    }
}
