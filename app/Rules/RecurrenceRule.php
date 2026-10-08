<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Subset of an RFC 5545 RRULE value, e.g. "FREQ=WEEKLY;INTERVAL=2;BYDAY=TU".
 * Interoperable with iCalendar; expansion is implemented in a later phase.
 */
class RecurrenceRule implements ValidationRule
{
    private const PARTS = [
        'FREQ' => '/^(DAILY|WEEKLY|MONTHLY|YEARLY)$/',
        'INTERVAL' => '/^[1-9]\d{0,2}$/',
        'COUNT' => '/^[1-9]\d{0,3}$/',
        'UNTIL' => '/^\d{8}(T\d{6}Z?)?$/',
        'BYDAY' => '/^([+-]?[1-5]?(MO|TU|WE|TH|FR|SA|SU))(,[+-]?[1-5]?(MO|TU|WE|TH|FR|SA|SU))*$/',
        'BYMONTHDAY' => '/^-?([1-9]|[12]\d|3[01])(,-?([1-9]|[12]\d|3[01]))*$/',
        'BYMONTH' => '/^([1-9]|1[0-2])(,([1-9]|1[0-2]))*$/',
        'BYSETPOS' => '/^-?[1-5](,-?[1-5])*$/',
        'WKST' => '/^(MO|TU|WE|TH|FR|SA|SU)$/',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $parts = [];
        foreach (explode(';', strtoupper((string) $value)) as $pair) {
            [$key, $val] = array_pad(explode('=', $pair, 2), 2, null);
            if ($val === null || ! isset(self::PARTS[$key]) || isset($parts[$key]) || preg_match(self::PARTS[$key], $val) !== 1) {
                $fail('Die Wiederholungsregel ist ungültig (RFC-5545-RRULE, z. B. FREQ=WEEKLY;BYDAY=TU).');

                return;
            }
            $parts[$key] = $val;
        }

        if (! isset($parts['FREQ']) || (isset($parts['COUNT']) && isset($parts['UNTIL']))) {
            $fail('Die Wiederholungsregel benötigt FREQ und darf COUNT und UNTIL nicht gleichzeitig enthalten.');
        }
    }
}
