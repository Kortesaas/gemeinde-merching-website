<?php

namespace App\Http\Controllers\Admin\Account;

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
 * Enrolment and management of TOTP two-factor authentication for the
 * signed-in employee.
 */
class TwoFactorSetupController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticator $twoFactor,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request): View
    {
        $user = $this->user($request);

        if ($user->hasEnabledTwoFactor()) {
            return view('admin.account.two-factor', [
                'enabled' => true,
                'remainingRecoveryCodes' => $this->twoFactor->remainingRecoveryCodes($user),
            ]);
        }

        $secret = $this->twoFactor->beginEnrolment($user);

        return view('admin.account.two-factor', [
            'enabled' => false,
            'qrCode' => $this->twoFactor->qrCodeDataUri($user),
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    /**
     * Confirm enrolment. Recovery codes are rendered directly in this response
     * (no redirect) so they are never stored in the session.
     */
    public function confirm(Request $request, AdminAuthenticator $authenticator): View|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasEnabledTwoFactor()) {
            return redirect()->route('admin.two-factor.setup');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        $codes = $this->twoFactor->confirmEnrolment($user, $data['code']);

        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'Der Code ist ungültig oder abgelaufen.']);
        }

        $authenticator->markTwoFactorVerified($request);
        $this->audit->record('auth.mfa_enabled', $user, actor: $user);

        return view('admin.account.recovery-codes', ['codes' => $codes, 'justEnabled' => true]);
    }

    public function regenerateRecoveryCodes(Request $request): View|RedirectResponse
    {
        $user = $this->user($request);

        if (! $user->hasEnabledTwoFactor()) {
            return redirect()->route('admin.two-factor.setup');
        }

        $request->validate(['current_password' => ['required', 'string', 'current_password']]);
        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        $this->audit->record('auth.recovery_codes_regenerated', $user, actor: $user);

        return view('admin.account.recovery-codes', ['codes' => $codes, 'justEnabled' => false]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
