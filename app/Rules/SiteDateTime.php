<?php

namespace App\Rules;

use App\Support\SiteTime;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Value of <input type="datetime-local"> in SITE_TIMEZONE, including the DST
 * rules of SiteTime (non-existent local times are rejected).
 */
class SiteDateTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            SiteTime::fromInput(is_string($value) ? $value : '');
        } catch (InvalidArgumentException $e) {
            $fail($e->getMessage());
        }
    }
}
