<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Controlled blocks accept plain text/safe Markdown, never executable markup. */
class ControlledText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/<\/?[a-z][^>]*>|<!--|(?:javascript|vbscript|data)\s*:|!\[[^\]]*\]\(/iu', $value)) {
            $fail('Bitte nur Text oder sichere Markdown-Links eingeben. HTML, Skripte und eingebettete Bilder sind nicht erlaubt.');
        }
    }
}
