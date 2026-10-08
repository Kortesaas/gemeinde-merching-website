<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A business rule was violated (route conflict, redirect loop, document still
 * in use, …). Carries the form field it belongs to for accessible errors.
 */
class DomainRuleViolation extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'general')
    {
        parent::__construct($message);
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([$this->field => $this->getMessage()]);
    }
}
