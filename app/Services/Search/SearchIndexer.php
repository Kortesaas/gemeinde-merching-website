<?php

namespace App\Services\Search;

use App\Contracts\Searchable;
use App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class SearchIndexer
{
    public const TYPES = [
        'page' => Models\Page::class, 'article' => Models\Article::class, 'service' => Models\Service::class,
        'life-situation' => Models\LifeSituation::class, 'event' => Models\Event::class, 'notice' => Models\PublicNotice::class,
        'document' => Models\Document::class, 'person' => Models\Person::class, 'department' => Models\Department::class,
        'organization' => Models\Organization::class, 'location' => Models\Location::class,
    ];

    public function sync(Model $model): void
    {
        if (! $model instanceof Searchable || ! in_array($model::class, self::TYPES, true)) {
            return;
        }
        if (($model->getAttributes()['deleted_at'] ?? null) !== null) {
            $this->remove($model);

            return;
        }
        $fresh = $model->fresh();
        if (! $fresh instanceof Searchable) {
            return;
        }
        $d = $fresh->toSearchDocument();
        DB::table('search_entries')->updateOrInsert(['content_type' => $model->getMorphClass(), 'content_id' => $model->getKey()], [
            'title' => $d->title, 'summary' => $d->summary, 'keywords' => implode(' ', $d->keywords), 'body' => strip_tags($d->body), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function remove(Model $model): void
    {
        DB::table('search_entries')->where('content_type', $model->getMorphClass())->where('content_id', $model->getKey())->delete();
    }

    public function rebuild(): int
    {
        $count = 0;
        DB::transaction(function () use (&$count) {
            DB::table('search_entries')->delete();
            foreach (self::TYPES as $class) {
                foreach ($class::query()->lazyById(200) as $model) {
                    $this->sync($model);
                    $count++;
                }
            }
        });

        return $count;
    }
}
