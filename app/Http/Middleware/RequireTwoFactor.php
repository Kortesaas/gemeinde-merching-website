<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the MFA policy for backend routes:
 * - accounts with MFA must have passed the second factor in this session;
 * - accounts without MFA are sent to enrolment when MFA is required.
 */
class RequireTwoFactor
{
    public function __construct(private readonly AdminAuthenticator $authenticator) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            if ($request->session()->get(AdminAuthenticator::MFA_VERIFIED) !== true) {
                $this->authenticator->logout($request, 'mfa_missing');

                return redirect()->route('admin.login');
            }

            return $next($request);
        }

        if (config('admin.mfa.required') === true) {
            return redirect()->route('admin.two-factor.setup')
                ->with('status', 'Bitte richten Sie zuerst die Zwei-Faktor-Authentisierung ein.');
        }

        return $next($request);
    }
}
