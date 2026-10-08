<?php

namespace App\Models;

use App\Enums\CategoryContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property CategoryContext $context
 * @property string $name
 * @property string $slug
 */
#[Fillable(['context', 'name', 'slug', 'description', 'sort_order'])]
class Category extends Model
{
    protected function casts(): array
    {
        return ['context' => CategoryContext::class, 'sort_order' => 'integer'];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeFor(Builder $query, CategoryContext $context): void
    {
        $query->where('context', $context->value)->orderBy('sort_order')->orderBy('name');
    }

    public function displayTitle(): string
    {
        return $this->name;
    }
}
