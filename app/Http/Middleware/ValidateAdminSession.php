<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends backend sessions of deactivated accounts and enforces the absolute
 * session lifetime (config('admin.session.absolute_lifetime')).
 */
class ValidateAdminSession
{
    public function __construct(private readonly AdminAuthenticator $authenticator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $authenticatedAt = $request->session()->get(AdminAuthenticator::AUTHENTICATED_AT);
        $maxSeconds = (int) config('admin.session.absolute_lifetime') * 60;

        $expired = ! is_int($authenticatedAt) || now()->getTimestamp() - $authenticatedAt > $maxSeconds;

        if (! $user instanceof User || ! $user->is_active || $expired) {
            $this->authenticator->logout($request, $expired ? 'session_expired' : 'account_inactive');

            return redirect()->route('admin.login')
                ->with('status', 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.');
        }

        return $next($request);
    }
}
