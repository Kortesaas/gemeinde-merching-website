<?php

namespace App\Support\Routing;

/** Configured listing addresses share the canonical rules of managed content. */
final class PublicSections
{
    /** @return array{path:string,title:string,kind:string}|null */
    public static function resolve(string $requestPath): ?array
    {
        /** @var array<string,array{title:string,kind:string}> $sections */
        $sections = config('public.sections', []);
        foreach ($sections as $path => $section) {
            if (PublicPath::key($path) === PublicPath::keyForRequest($requestPath)) {
                return ['path' => $path, ...$section];
            }
        }

        return null;
    }
}
