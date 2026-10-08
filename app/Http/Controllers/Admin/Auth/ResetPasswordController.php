<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
                'token' => $data['token'],
                'is_active' => true,
            ],
            function (User $user, string $password) use ($audit) {
                $user->forceFill([
                    'password' => $password,
                    'password_changed_at' => now(),
                ])->save();

                // End every existing session of this account.
                DB::table((string) config('session.table'))->where('user_id', $user->getKey())->delete();

                $audit->record('auth.password_reset', $user, actor: $user);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Generic message: do not reveal whether the account or token exists.
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('passwords.token')]);
        }

        return redirect()->route('admin.login')
            ->with('status', 'Ihr Passwort wurde geändert. Bitte melden Sie sich mit dem neuen Passwort an.');
    }
}
