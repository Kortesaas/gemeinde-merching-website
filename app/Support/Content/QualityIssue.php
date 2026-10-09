<?php

namespace App\Support\Content;

use App\Enums\QualitySeverity;

final readonly class QualityIssue
{
    public function __construct(public string $code, public QualitySeverity $severity, public string $message, public ?string $field = null) {}
}
