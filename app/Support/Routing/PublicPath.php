<?php

namespace App\Support\Routing;

use InvalidArgumentException;

/**
 * Normalisation of public URL paths (content routes and redirects).
 *
 * URL convention: canonical paths have NO trailing slash ("/aktuelles",
 * "/buergerservice/personalausweis"); only the root is "/".
 *
 * - "path" is the canonical form: decoded UTF-8, case preserved, trailing
 *   slash removed. It is what internal links, canonical tags and sitemaps use.
 * - "key" is the lookup/uniqueness key: lower-case path. Variants
 *   ("/Aktuelles", "/aktuelles/") share one key and are redirected with a
 *   single 301 to the canonical path (legacy URLs with slashes keep working).
 */
final class PublicPath
{
    public const MAX_LENGTH = 255;

    /**
     * @return array{path: string, key: string}
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string $raw): array
    {
        $path = trim($raw);

        if ($path === '' || ! str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Die Adresse muss mit „/“ beginnen.');
        }

        if (preg_match('/[?#\\\\\s]/u', $path) === 1 || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException('Die Adresse darf keine Leerzeichen, „?“, „#“ oder „\\“ enthalten.');
        }

        $path = rawurldecode($path);

        if (! mb_check_encoding($path, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException('Die Adresse enthält ungültige Zeichen.');
        }

        if (str_contains($path, '//') || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1) {
            throw new InvalidArgumentException('Die Adresse darf keine leeren Abschnitte oder „..“ enthalten.');
        }

        if (mb_strlen($path) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Die Adresse ist zu lang (max. '.self::MAX_LENGTH.' Zeichen).');
        }

        $path = self::withoutTrailingSlash($path);

        return ['path' => $path, 'key' => self::key($path)];
    }

    public static function withoutTrailingSlash(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /**
     * Lookup key of an (already decoded) path.
     */
    public static function key(string $path): string
    {
        $key = rtrim(mb_strtolower($path), '/');

        return $key === '' ? '/' : $key;
    }

    /**
     * Lookup key for an incoming request path (raw, percent-encoded).
     */
    public static function keyForRequest(string $requestPath): string
    {
        return self::key(rawurldecode($requestPath));
    }

    /**
     * Paths owned by the application itself; content and redirects may not use them.
     */
    public static function isReserved(string $key): bool
    {
        $reserved = ['/', '/'.config('admin.path'), '/build', '/download', '/robots.txt', '/kontakt', '/medien', '/sitemap.xml', '/sitemap', '/index.php', '/storage', '/.well-known', '/up'];

        foreach ($reserved as $prefix) {
            if ($key === $prefix || ($prefix !== '/' && str_starts_with($key, $prefix.'/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Percent-encoded form for links and Location headers.
     */
    public static function toUrl(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Absolute canonical URL on the canonical origin (APP_URL), e.g. for
     * <link rel="canonical"> and XML sitemaps. Never has a trailing slash
     * (except the root).
     */
    public static function absoluteUrl(string $path): string
    {
        $origin = rtrim((string) config('app.url'), '/');

        return $origin.self::toUrl(self::withoutTrailingSlash($path));
    }
}
