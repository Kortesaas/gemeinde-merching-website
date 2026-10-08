<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AdminAuthenticator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function __construct(private readonly AdminAuthenticator $authenticator) {}

    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->authenticator->validateCredentials($request, $credentials['email'], $credentials['password']);

        if ($user->hasEnabledTwoFactor()) {
            $this->authenticator->beginTwoFactorChallenge($request, $user);

            return redirect()->route('admin.two-factor.challenge');
        }

        $this->authenticator->completeLogin($request, $user, mfaVerified: false);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authenticator->logout($request);

        return redirect()->route('admin.login')->with('status', 'Sie wurden abgemeldet.');
    }
}
