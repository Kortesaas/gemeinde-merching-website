<?php

namespace App\Services\Quality;

use App\Contracts\QualityCheck;
use App\Enums\QualitySeverity;
use App\Exceptions\DomainRuleViolation;
use App\Support\Content\QualityIssue;
use Illuminate\Database\Eloquent\Model;

final class QualityChecks
{
    /** @var list<QualityCheck> */
    private array $checks;

    public function __construct(ContentBasics $basics, ReferencedAssets $assets)
    {
        $this->checks = [$basics, $assets];
    }

    public function add(QualityCheck $check): void
    {
        $this->checks[] = $check;
    }

    /** @return list<QualityIssue> */
    public function inspect(Model $model): array
    {
        $issues = [];
        foreach ($this->checks as $check) {
            if ($check->supports($model)) {
                $issues = [...$issues, ...$check->inspect($model)];
            }
        }

        return $issues;
    }

    public function enforcePublicAssets(Model $model): void
    {
        if (! method_exists($model, 'isPublicationLocked') || ! $model->isPublicationLocked()) {
            return;
        }
        foreach ($this->inspect($model) as $issue) {
            if ($issue->severity === QualitySeverity::Error) {
                throw new DomainRuleViolation($issue->message, $issue->field ?? 'general');
            }
        }
    }
}
