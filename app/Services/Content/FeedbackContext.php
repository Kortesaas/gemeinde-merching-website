<?php

namespace App\Services\Content;

use App\Contracts\Routable;
use App\Models\PublicRoute;
use App\Support\Routing\PublicPath;
use InvalidArgumentException;

final class FeedbackContext
{
    /** @return array{path:string,title:string,type:string}|null */
    public function resolve(string $path): ?array
    {
        try {
            $normalized = PublicPath::normalize($path);
        } catch (InvalidArgumentException) {
            return null;
        }
        if ($normalized['path'] !== $path || PublicPath::isReserved($normalized['key'])) {
            return null;
        }
        $route = PublicRoute::query()->where('path', $path)->where('is_canonical', true)->where('is_active', true)->first();
        $model = $route?->routable;
        if (! $model instanceof Routable || ! $model->isPubliclyReachable()) {
            return null;
        }

        return ['path' => $route->path, 'title' => $model->displayTitle(), 'type' => $model->getMorphClass()];
    }
}
