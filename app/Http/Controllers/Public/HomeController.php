<?php

namespace App\Http\Controllers\Public;

use App\Enums\NavigationMenu;
use App\Http\Controllers\Controller;
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

        return view('public.home', [
            'siteTitle' => $settings->municipality_name ?? config('app.name'),
            'townHall' => $settings?->townHall,
            'central' => $settings?->centralDepartment,
            'shortcuts' => $navigation->tree(NavigationMenu::Service),
            'news' => $catalog->items('articles')->take(4),
            'events' => $catalog->items('events')->filter(fn ($m) => $m instanceof Event && $m->endsAtForArchiving()->isFuture())->take(4),
            'online' => ExternalResource::query()->visible()->where('type', 'online_service')->limit(6)->get(),
        ]);
    }
}
