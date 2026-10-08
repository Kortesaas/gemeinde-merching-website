<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

use function Illuminate\Support\defer;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        // Only active accounts receive a link. The mail is sent after the
        // response so neither the message nor the response time reveals whether
        // an account exists.
        Password::broker()->sendResetLink(
            ['email' => mb_strtolower(trim($data['email'])), 'is_active' => true],
            function (User $user, string $token) {
                defer(fn () => $user->sendPasswordResetNotification($token));
            },
        );

        return back()->with('status', __('passwords.sent'));
    }
}
