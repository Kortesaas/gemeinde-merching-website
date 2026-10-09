<?php

namespace App\Services\Search;

use App\Contracts\Routable;
use App\Models\Article;
use App\Models\ContentBlock;
use App\Models\Department;
use App\Models\Document;
use App\Models\Page;
use App\Models\Person;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;

final class SearchVisibility
{
    public function path(Model $model): ?string
    {
        if ($model instanceof Document) {
            return $model->isPubliclyReachable() ? $model->downloadPath() : null;
        }
        if ($model instanceof Routable) {
            return $model->isPubliclyReachable() ? $model->publicPath() : null;
        }
        if (! $model instanceof Person || ! $model->is_active || $model->trashed()) {
            return null;
        }
        foreach (ContentBlock::query()->where('person_id', $model->getKey())->get() as $block) {
            $owner = $block->owner;
            if ($owner instanceof Routable && $owner->isPubliclyReachable() && $owner->publicPath() !== null) {
                return $owner->publicPath();
            }
        }
        // People have no profile URL; results link to a public context where they are referenced.
        foreach ([Service::class, Department::class, Page::class, Article::class] as $class) {
            $relation = $class === Department::class ? 'people' : 'contacts';
            foreach ($class::query()->with('canonicalRoute')->whereHas($relation, fn ($q) => $q->where('people.id', $model->getKey()))->orderBy('id')->get() as $owner) {
                if ($owner->isPubliclyReachable() && $owner->publicPath() !== null) {
                    return $owner->publicPath();
                }
            }
        }

        return null;
    }
}
