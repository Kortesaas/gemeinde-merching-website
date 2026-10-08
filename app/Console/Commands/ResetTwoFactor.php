<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TwoFactorAuthenticator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lock-out recovery: removes MFA from an account (e.g. lost phone and no
 * recovery codes left). The account must enrol again at the next login.
 * Requires shell access to the server, which is itself strongly protected.
 */
#[Signature('admin:reset-mfa {email : E-mail address of the account}')]
#[Description('Remove two-factor authentication from an account and end its sessions')]
class ResetTwoFactor extends Command
{
    public function handle(TwoFactorAuthenticator $twoFactor, AuditLogger $audit): int
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->components->error('No account with this e-mail address.');

            return self::FAILURE;
        }

        if (! $this->confirm("Remove two-factor authentication for {$user->email}? Only do this after verifying the person's identity.")) {
            return self::FAILURE;
        }

        $twoFactor->disable($user);
        DB::table((string) config('session.table'))->where('user_id', $user->getKey())->delete();
        $audit->record('auth.mfa_reset', $user, ['via' => 'cli']);

        $this->components->info('Two-factor authentication removed. The user must set it up again at the next login.');

        return self::SUCCESS;
    }
}
