<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use SensitiveParameter;

/**
 * TOTP (RFC 6238) two-factor authentication and recovery codes.
 *
 * Cryptography is delegated to pragmarx/google2fa (TOTP), Laravel's encrypter
 * (secret at rest) and PHP's HMAC implementation (recovery-code hashes).
 */
class TwoFactorAuthenticator
{
    /** Alphabet for recovery codes without easily confused characters. */
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Start (or continue) enrolment: ensure the user has an unconfirmed secret.
     */
    public function beginEnrolment(User $user): string
    {
        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
            $user->forceFill([
                'two_factor_secret' => $this->google2fa->generateSecretKey(32),
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_timestep' => null,
            ])->save();
        }

        return (string) $user->two_factor_secret;
    }

    /**
     * Confirm enrolment with a first valid code and issue recovery codes.
     *
     * @return list<string>|null plain recovery codes (show once) or null if the code is invalid
     */
    public function confirmEnrolment(User $user, #[SensitiveParameter] string $code): ?array
    {
        if ($user->two_factor_secret === null || ! $this->verifyCode($user, $code)) {
            return null;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $this->regenerateRecoveryCodes($user);
    }

    /**
     * Verify a TOTP code. Each time step can only be used once (replay protection).
     */
    public function verifyCode(User $user, #[SensitiveParameter] string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($user->two_factor_secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $timestep = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            $code,
            // 0 instead of null makes the library return the matched time step.
            $user->two_factor_last_used_timestep ?? 0,
            1, // accept ±30 seconds of clock drift
        );

        if (! is_int($timestep)) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_timestep' => $timestep])->save();

        return true;
    }

    /**
     * Consume a recovery code. Returns false if unknown or already used.
     */
    public function useRecoveryCode(User $user, #[SensitiveParameter] string $code): bool
    {
        $hash = $this->hashRecoveryCode($code);

        return DB::transaction(function () use ($user, $hash) {
            $updated = $user->recoveryCodes()
                ->where('code_hash', $hash)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return $updated === 1;
        });
    }

    /**
     * Replace all recovery codes. Returns the plain codes (to be shown once).
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < (int) config('admin.mfa.recovery_codes'); $i++) {
            $codes[] = $this->randomRecoveryCode();
        }

        DB::transaction(function () use ($user, $codes) {
            $user->recoveryCodes()->delete();
            foreach ($codes as $code) {
                $user->recoveryCodes()->create(['code_hash' => $this->hashRecoveryCode($code)]);
            }
        });

        return $codes;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return $user->recoveryCodes()->whereNull('used_at')->count();
    }

    /**
     * Remove MFA completely (used by the admin:reset-mfa command).
     */
    public function disable(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->recoveryCodes()->delete();
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_timestep' => null,
            ])->save();
        });
    }

    /**
     * otpauth:// URI for authenticator apps.
     */
    public function provisioningUri(User $user): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('admin.mfa.issuer'),
            $user->email,
            (string) $user->two_factor_secret,
        );
    }

    /**
     * QR code as an SVG data URI. Rendered locally – the secret never leaves
     * the server, and the image is CSP-compatible (img-src data:).
     */
    public function qrCodeDataUri(User $user): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 2), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($this->provisioningUri($user)));
    }

    private function randomRecoveryCode(): string
    {
        $chars = '';
        for ($i = 0; $i < 10; $i++) {
            $chars .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
        }

        return substr($chars, 0, 5).'-'.substr($chars, 5);
    }

    /**
     * Recovery codes have ~50 bits of entropy, so a keyed fast hash is
     * appropriate and allows a direct indexed lookup.
     */
    private function hashRecoveryCode(#[SensitiveParameter] string $code): string
    {
        $normalized = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return hash_hmac('sha256', 'mfa-recovery:'.$normalized, (string) config('app.key'));
    }
}
