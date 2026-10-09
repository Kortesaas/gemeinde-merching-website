<?php

namespace App\Http\Controllers\Public;

use App\Services\Seo\Sitemap;
use App\Support\Content\SeoUrl;
use Illuminate\Http\Response;

class SitemapController
{
    public function __invoke(Sitemap $sitemap, ?int $page = null): Response
    {
        $entries = $sitemap->entries();
        $pages = (int) ceil(count($entries) / Sitemap::PAGE_SIZE);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $escape = fn (string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if ($pages > 1 && $page === null) {
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            for ($i = 1; $i <= $pages; $i++) {
                $xml .= '<sitemap><loc>'.$escape(SeoUrl::path('/sitemap/'.$i.'.xml')).'</loc></sitemap>';
            }
            $xml .= '</sitemapindex>';
        } else {
            abort_if($page !== null && ($page < 1 || $page > $pages), 404);
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach (array_slice($entries, (($page ?? 1) - 1) * Sitemap::PAGE_SIZE, Sitemap::PAGE_SIZE) as $entry) {
                $xml .= '<url><loc>'.$escape($entry['url']).'</loc>'.($entry['modified'] ? '<lastmod>'.$escape($entry['modified']).'</lastmod>' : '').'</url>';
            }
            $xml .= '</urlset>';
        }

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'no-cache']);
    }
}
