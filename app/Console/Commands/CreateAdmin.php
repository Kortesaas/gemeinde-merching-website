<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\RoleSynchronizer;
use App\Support\Authorization\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Interactively creates an administrator account. There are no default
 * credentials and the password is never accepted as a command-line argument
 * (it would end up in the shell history).
 */
#[Signature('admin:create')]
#[Description('Interactively create an administrator account')]
class CreateAdmin extends Command
{
    public function handle(RoleSynchronizer $roles, AuditLogger $audit): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('This command must be run interactively (the password is prompted for).');

            return self::FAILURE;
        }

        $name = text(
            label: 'Name',
            required: true,
            validate: fn (string $value) => mb_strlen($value) > 255 ? 'Maximal 255 Zeichen.' : null,
        );

        $email = mb_strtolower(trim(text(
            label: 'E-Mail-Adresse (Anmeldename)',
            required: true,
            validate: fn (string $value) => $this->validationError(
                ['email' => mb_strtolower(trim($value))],
                ['email' => ['email', 'max:255', 'unique:users,email']],
            ),
        )));

        $password = password(
            label: 'Passwort (mind. 12 Zeichen)',
            required: true,
            validate: fn (string $value) => $this->validationError(
                ['password' => $value],
                ['password' => [Password::defaults()]],
            ),
        );

        password(
            label: 'Passwort wiederholen',
            required: true,
            validate: fn (string $value) => hash_equals($password, $value) ? null : 'Die Passwörter stimmen nicht überein.',
        );

        $roles->sync();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);
        $user->forceFill(['password_changed_at' => now()])->save();
        $user->assignRole(Role::Administrator->value);

        $audit->record('user.created', $user, ['via' => 'cli', 'role' => Role::Administrator->value]);

        $this->components->info("Administrator account created for {$email}.");

        if (config('admin.mfa.required')) {
            $this->components->warn('Two-factor authentication is required: an authenticator app (TOTP) '
                .'must be set up during the first login at /'.config('admin.path').'/login.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     */
    private function validationError(array $data, array $rules): ?string
    {
        $validator = Validator::make($data, $rules);

        return $validator->fails() ? $validator->errors()->first() : null;
    }
}
