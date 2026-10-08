<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Knows which requests belong to the employee backend (config('admin.path')).
 */
final class AdminArea
{
    public static function path(): string
    {
        return (string) config('admin.path');
    }

    public static function matches(Request $request): bool
    {
        $path = self::path();

        return $request->is($path, $path.'/*');
    }
}
