<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class BudgetPlanPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::BudgetPlan;
    }
}
