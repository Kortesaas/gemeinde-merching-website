<?php

namespace App\Support;

use App\Models;

/**
 * Stable aliases stored in polymorphic columns (routes, revisions, audit log,
 * source references) instead of PHP class names.
 */
final class MorphMap
{
    public const MAP = [
        'gallery' => Models\Gallery::class,
        'wahlperioden' => Models\CouncilTerm::class,
        'ratsmitglieder' => Models\CouncilMember::class,
        'ausschuesse' => Models\Committee::class,
        'media' => Models\Media::class,
        'search-synonym' => Models\SearchSynonym::class,
        'site-settings' => Models\SiteSettings::class,
        'user' => Models\User::class,
        'article' => Models\Article::class,
        'event' => Models\Event::class,
        'document' => Models\Document::class,
        'budget-plan' => Models\BudgetPlan::class,
        'external-resource' => Models\ExternalResource::class,
        'notice' => Models\PublicNotice::class,
        'service' => Models\Service::class,
        'life-situation' => Models\LifeSituation::class,
        'page' => Models\Page::class,
        'site-alert' => Models\SiteAlert::class,
        'person' => Models\Person::class,
        'department' => Models\Department::class,
        'organization' => Models\Organization::class,
        'location' => Models\Location::class,
        'contact-route' => Models\ContactRoute::class,
        'category' => Models\Category::class,
        'tag' => Models\Tag::class,
        'navigation-item' => Models\NavigationItem::class,
        'redirect' => Models\Redirect::class,
        'public-route' => Models\PublicRoute::class,
        'proposal' => Models\ContentProposal::class,
    ];
}
