<?php

namespace App\Http\Controllers\Public;

use App\Enums\NavigationMenu;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Event;
use App\Models\ExternalResource;
use App\Services\Content\PublicCatalog;
use App\Services\Navigation\NavigationManager;
use App\Services\Settings\SiteConfiguration;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(PublicCatalog $catalog, NavigationManager $navigation): View
    {
        $settings = app(SiteConfiguration::class)->current();
        $public = fn ($model) => $model?->isPubliclyReachable() ? $model : null;
        $hero = $settings?->homepageMedia;
        $news = $catalog->items('articles')->take(4)->values();
        $news->each(fn ($article) => $article instanceof Article ? $article->load('media') : null);
        $resources = ExternalResource::query()->visible()->whereIn('type', ['online_service', 'portal'])->orderBy('title')->get();

        return view('public.home', [
            'siteTitle' => $settings->municipality_name ?? config('app.name'),
            'townHall' => $public($settings?->townHall),
            'central' => $public($settings?->centralDepartment),
            'works' => $public($settings?->worksDepartment),
            'recycling' => $public($settings?->recyclingLocation),
            'hero' => $hero !== null && $hero->isPubliclyReachable() && $hero->isImage() && $hero->hasAccessibleAlternative() ? $hero : null,
            'shortcuts' => $navigation->tree(NavigationMenu::Service),
            'news' => $news,
            'events' => $catalog->items('events')->filter(fn ($m) => $m instanceof Event && $m->endsAtForArchiving()->isFuture())->take(4)->values(),
            'portal' => $resources->first(fn ($r) => $r->getRawOriginal('type') === 'portal'),
            'online' => $resources->filter(fn ($r) => $r->getRawOriginal('type') === 'online_service')->take(5)->values(),
        ]);
    }
}
