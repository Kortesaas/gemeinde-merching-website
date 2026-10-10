<?php

namespace App\Support\Content;

/** Decorate anchor start tags without reparsing/reformatting the page's HTML. */
final class NewTabLinks
{
    /** @param list<string> $documentPaths */
    public static function apply(string $html, string $origin, array $documentPaths): string
    {
        $documents = array_fill_keys(array_map('rawurldecode', $documentPaths), true);

        return preg_replace_callback('~<a\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i', function (array $match) use ($origin, $documents): string {
            $tag = $match[0];
            $href = self::attribute($tag, 'href');
            if ($href === null) {
                return $tag;
            }
            $url = parse_url($href);
            if ($url === false || isset($url['scheme']) && ! in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
                return $tag;
            }
            $originUrl = parse_url($origin);
            $external = isset($url['host']) && (strtolower($url['host']) !== strtolower($originUrl['host'] ?? '')
                || ($url['port'] ?? (strtolower($url['scheme'] ?? 'https') === 'https' ? 443 : 80)) !== ($originUrl['port'] ?? (($originUrl['scheme'] ?? 'https') === 'https' ? 443 : 80)));
            $path = rawurldecode($url['path'] ?? '');
            $document = isset($documents[$path]) || str_starts_with($path, '/download/')
                || preg_match('/\.(pdf|docx?|xlsx?|pptx?|odt|ods|odp|rtf|csv|zip)$/i', $path) === 1;
            if (! $external && ! $document) {
                return $tag;
            }
            $tag = self::setAttribute($tag, 'target', '_blank');
            $rel = preg_split('/\s+/', trim(self::attribute($tag, 'rel') ?? '')) ?: [];
            $tag = self::setAttribute($tag, 'rel', implode(' ', array_unique(array_filter([...$rel, 'noopener', 'noreferrer']))));
            $descriptions = preg_split('/\s+/', trim(self::attribute($tag, 'aria-describedby') ?? '')) ?: [];

            return self::setAttribute($tag, 'aria-describedby', implode(' ', array_unique(array_filter([...$descriptions, 'new-tab-description']))));
        }, $html) ?? $html;
    }

    private static function attribute(string $tag, string $name): ?string
    {
        return preg_match('~\s'.preg_quote($name, '~').'\s*=\s*(?:"([^"]*)"|\'([^\']*)\')~i', $tag, $match) === 1
            ? html_entity_decode($match[1] !== '' ? $match[1] : ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    private static function setAttribute(string $tag, string $name, string $value): string
    {
        $tag = preg_replace('~\s'.preg_quote($name, '~').'\s*=\s*(?:"[^"]*"|\'[^\']*\')~i', '', $tag) ?? $tag;

        return substr($tag, 0, -1).' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
    }
}
