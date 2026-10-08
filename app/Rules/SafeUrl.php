<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Absolute http(s) URL without credentials, control characters or exotic
 * schemes (javascript:, data:, file:, …). Used for every editor-entered link.
 */
class SafeUrl implements ValidationRule
{
    public function __construct(private readonly bool $httpsOnly = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSafe($value, $this->httpsOnly)) {
            $fail($this->httpsOnly
                ? 'Bitte geben Sie eine vollständige, sichere Adresse ein (beginnend mit https://).'
                : 'Bitte geben Sie eine vollständige Internetadresse ein (beginnend mit https:// oder http://).');
        }
    }

    public static function isSafe(string $url, bool $httpsOnly = false): bool
    {
        if ($url !== trim($url) || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $schemes = $httpsOnly ? ['https'] : ['https', 'http'];

        return in_array(strtolower($parts['scheme']), $schemes, true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
