<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['budget_source_id', 'sort_order'])]
class BudgetComponent extends Model
{
    /** @return BelongsTo<BudgetSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(BudgetSource::class, 'budget_source_id');
    }
}
