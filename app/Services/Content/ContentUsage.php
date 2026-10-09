<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentProposal;
use App\Models\ContentRevision;
use App\Models\Document;
use App\Models\Event;
use App\Models\ExternalResource;
use App\Models\LifeSituation;
use App\Models\Location;
use App\Models\Media;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PublicNotice;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;

/**
 * Answers "Wo wird diese Datei / dieser Link verwendet?" and guards permanent
 * deletion: a referenced record (also by owners in the recycle bin) is never
 * destroyed. Database foreign keys (RESTRICT) are the second line of defence.
 */
class ContentUsage
{
    /**
     * @return list<array{owner: Model, context: string}>
     */
    public function of(Document|ExternalResource|Media $item): array
    {
        if ($item instanceof Media) {
            $usages = app(ReferenceProtection::class)->usages($item);
            $usages = [...$usages, ...array_map(fn (Model $owner) => ['owner' => $owner, 'context' => 'Medienzuordnung'], $this->mediaOwners($item, false))];
            foreach (ContentRevision::query()->cursor() as $revision) {
                if ($this->snapshotUsesMedia($revision->snapshot, (int) $item->getKey())) {
                    $usages[] = ['owner' => $item, 'context' => 'Versionsgeschichte #'.$revision->getKey()];
                }
            }
            foreach (ContentProposal::query()->cursor() as $proposal) {
                if ($this->snapshotUsesMedia($proposal->payload, (int) $item->getKey()) || $this->snapshotUsesMedia($proposal->base_snapshot, (int) $item->getKey())) {
                    $usages[] = ['owner' => $item, 'context' => 'Änderungsvorschlag #'.$proposal->getKey()];
                }
            }

            return $usages;
        }
        $usages = app(ReferenceProtection::class)->usages($item);

        foreach ($item->placementRelations() as $relation) {
            foreach ($relation->get() as $owner) {
                /** @var Model $owner */
                $method = $item instanceof Document ? 'documentSlots' : 'resourceSlots';
                /** @var array<string, string> $slots */
                $slots = is_callable([$owner::class, $method]) ? call_user_func([$owner::class, $method]) : [];
                $pivot = $owner->getRelation('pivot');
                $slot = $slots[$pivot->getAttribute('slot')] ?? $pivot->getAttribute('slot');
                $group = $pivot->getAttribute('group_label');
                $usages[] = ['owner' => $owner, 'context' => trim($slot.($group ? ' › '.$group : ''))];
            }
        }

        if ($item instanceof Document) {
            if ($item->replacedBy !== null) {
                $usages[] = ['owner' => $item->replacedBy, 'context' => 'Ersetzt durch dieses Dokument'];
            }
            foreach ($item->alternativeFor as $original) {
                $usages[] = ['owner' => $original, 'context' => 'Barrierefreie Alternative zu diesem Dokument'];
            }
        } else {
            foreach (NavigationItem::query()->where('external_resource_id', $item->getKey())->get() as $navigation) {
                $usages[] = ['owner' => $navigation, 'context' => 'Navigationsziel'];
            }
            foreach (Service::withTrashed()->where('online_service_resource_id', $item->getKey())->get() as $service) {
                $usages[] = ['owner' => $service, 'context' => 'Online-Dienst der Leistung'];
            }
            foreach (Location::withTrashed()->where('map_resource_id', $item->getKey())->get() as $location) {
                $usages[] = ['owner' => $location, 'context' => 'Kartenlink des Ortes'];
            }
        }

        return $usages;
    }

    /** @return list<Model> */
    public function mediaOwners(Media $media, bool $includeCompositions = true): array
    {
        $owners = [];
        foreach ([Page::class, Article::class, Event::class, PublicNotice::class, Service::class, LifeSituation::class] as $class) {
            foreach ($class::withTrashed()->whereHas('media', fn ($q) => $q->where('media.id', $media->getKey()))->get() as $owner) {
                $owners[] = $owner;
            }
        }

        if ($includeCompositions) {
            foreach (app(ReferenceProtection::class)->liveOwners($media) as $owner) {
                $owners[] = $owner;
            }
        }

        return $owners;
    }

    /** @param array<string,mixed> $snapshot */
    private function snapshotUsesMedia(array $snapshot, int $id): bool
    {
        foreach ($snapshot['relations']['media'] ?? [] as $row) {
            if ((int) $row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    public function isUsed(Document|ExternalResource|Media $item): bool
    {
        return $this->of($item) !== [];
    }
}
