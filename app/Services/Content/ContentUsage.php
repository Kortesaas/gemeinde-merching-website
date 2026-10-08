<?php

namespace App\Services\Content;

use App\Models\Document;
use App\Models\ExternalResource;
use App\Models\Location;
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
    public function of(Document|ExternalResource $item): array
    {
        $usages = [];

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
            foreach (Service::withTrashed()->where('online_service_resource_id', $item->getKey())->get() as $service) {
                $usages[] = ['owner' => $service, 'context' => 'Online-Dienst der Leistung'];
            }
            foreach (Location::withTrashed()->where('map_resource_id', $item->getKey())->get() as $location) {
                $usages[] = ['owner' => $location, 'context' => 'Kartenlink des Ortes'];
            }
        }

        return $usages;
    }

    public function isUsed(Document|ExternalResource $item): bool
    {
        return $this->of($item) !== [];
    }
}
