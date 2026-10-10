<?php

namespace App\Services\Seo;

use App\Enums\EventOperationalStatus;
use App\Models\Article;
use App\Models\Event;
use App\Models\Service;
use App\Support\Content\SeoData;
use App\Support\Content\SeoUrl;
use App\Support\SiteTime;
use Illuminate\Database\Eloquent\Model;

/** Schema.org microdata: inert HTML, with no inline script or relaxed CSP. */
final class StructuredData
{
    /** @return array<string, mixed> */
    public function page(SeoData $seo, string $title, ?Model $model = null): array
    {
        $home = SeoUrl::path('/');
        $municipality = ['@type' => 'GovernmentOrganization', '@id' => $home.'#municipality', 'name' => $seo->siteName, 'url' => $home, 'logo' => SeoUrl::path((string) config('seo.logo'))];
        $website = ['@type' => 'WebSite', '@id' => $home.'#website', 'name' => $seo->siteName, 'url' => $home, 'inLanguage' => 'de-DE', 'publisher' => $municipality];
        $page = ['@type' => 'WebPage', '@id' => $seo->canonical.'#webpage', 'url' => $seo->canonical, 'name' => $title, 'description' => $seo->description, 'inLanguage' => 'de-DE', 'isPartOf' => $website,
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $seo->image['url'], 'width' => $seo->image['width'], 'height' => $seo->image['height'], 'caption' => $seo->image['alt']]];
        $entity = ['name' => $title, 'description' => $seo->description, 'url' => $seo->canonical, 'image' => $seo->image['url']];
        if ($model instanceof Article) {
            $entity = ['@type' => 'NewsArticle', ...$entity, 'headline' => $title, 'publisher' => $municipality, 'inLanguage' => 'de-DE'];
            if ($model->publish_at !== null) {
                $entity['datePublished'] = $model->publish_at->toAtomString();
            }
            if ($model->getAttribute('updated_at') !== null) {
                $entity['dateModified'] = $model->getAttribute('updated_at')->toAtomString();
            }
            if (trim((string) $model->getAttribute('author_name')) !== '') {
                $entity['author'] = ['@type' => 'Person', 'name' => $model->getAttribute('author_name')];
            }
            $page['mainEntity'] = $entity;
        } elseif ($model instanceof Event) {
            $entity = ['@type' => 'Event', ...$entity, 'startDate' => SiteTime::fromUtc($model->starts_at)->format(($model->all_day || $model->time_is_unspecified) ? 'Y-m-d' : 'c'),
                'eventStatus' => 'https://schema.org/'.($model->operational_status === EventOperationalStatus::Cancelled ? 'EventCancelled' : 'EventScheduled')];
            if ($model->ends_at !== null) {
                $entity['endDate'] = SiteTime::fromUtc($model->ends_at)->format(($model->all_day || $model->time_is_unspecified) ? 'Y-m-d' : 'c');
            }
            $location = $model->location;
            if ($location?->isPubliclyReachable()) {
                $entity['location'] = ['@type' => 'Place', 'name' => $location->name];
                $address = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $location->getAttribute('street'), 'postalCode' => $location->getAttribute('postal_code'), 'addressLocality' => $location->getAttribute('city')]);
                if (count($address) > 1) {
                    $entity['location']['address'] = $address;
                }
            } elseif (trim((string) $model->getAttribute('venue')) !== '') {
                $entity['location'] = ['@type' => 'Place', 'name' => $model->getAttribute('venue')];
            }
            $organization = $model->organization;
            $organizer = $organization?->isPubliclyReachable() ? $organization->displayTitle() : $model->getAttribute('organizer_name');
            if (is_string($organizer) && trim($organizer) !== '') {
                $entity['organizer'] = ['@type' => 'Organization', 'name' => $organizer];
            }
            $page['mainEntity'] = $entity;
        } elseif ($model instanceof Service) {
            $page['mainEntity'] = ['@type' => 'GovernmentService', ...$entity, 'provider' => $municipality];
        }

        return $page;
    }
}
