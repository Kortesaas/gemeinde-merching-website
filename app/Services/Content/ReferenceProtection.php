<?php

namespace App\Services\Content;

use App\Exceptions\DomainRuleViolation;
use App\Models;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Model;

/** Explicit child references remain protected in live, deleted and historical compositions. */
final class ReferenceProtection
{
    public const REFERENCES = Models\ContentBlock::REFERENCES + ['council_member_id' => Models\CouncilMember::class, 'council_term_id' => Models\CouncilTerm::class];

    /** @return list<Model> */
    public function liveOwners(Model $item): array
    {
        $column = array_search($item::class, self::REFERENCES, true);
        if ($column === false) {
            return [];
        }
        $owners = [];
        if (array_key_exists($column, Models\ContentBlock::REFERENCES)) {
            foreach (Models\ContentBlock::query()->where($column, $item->getKey())->get() as $block) {
                $class = MorphMap::MAP[$block->getAttribute('owner_type')] ?? null;
                $owner = $class ? $class::withTrashed()->find($block->getAttribute('owner_id')) : null;
                if ($owner instanceof Model) {
                    $owners[] = $owner;
                }
            }
        }
        if ($item instanceof Models\Media) {
            foreach (Models\Gallery::withTrashed()->whereHas('items', fn ($q) => $q->where('media_id', $item->getKey()))->get() as $owner) {
                $owners[] = $owner;
            }
        }
        if ($item instanceof Models\CouncilMember) {
            foreach (Models\CouncilTerm::withTrashed()->whereHas('memberships', fn ($q) => $q->where($column, $item->getKey()))->get() as $owner) {
                $owners[] = $owner;
            }
            foreach (Models\Committee::withTrashed()->whereHas('committeeMemberships', fn ($q) => $q->where($column, $item->getKey()))->get() as $owner) {
                $owners[] = $owner;
            }
        }
        if ($item instanceof Models\CouncilTerm) {
            foreach ($item->committees()->withTrashed()->get() as $owner) {
                $owners[] = $owner;
            }
        }

        return $owners;
    }

    /** @return list<array{owner:Model,context:string}> */
    public function usages(Model $item): array
    {
        $column = array_search($item::class, self::REFERENCES, true);
        if ($column === false) {
            return [];
        }
        $usages = array_map(fn (Model $owner) => ['owner' => $owner, 'context' => 'Inhaltsbaustein / strukturierte Zuordnung'], $this->liveOwners($item));
        foreach (Models\ContentRevision::query()->cursor() as $revision) {
            if ($this->snapshotUses($revision->snapshot, $column, (int) $item->getKey())) {
                $usages[] = ['owner' => $item, 'context' => 'Versionsgeschichte #'.$revision->getKey()];
            }
        }
        foreach (Models\ContentProposal::query()->cursor() as $proposal) {
            if ($this->snapshotUses($proposal->payload, $column, (int) $item->getKey()) || $this->snapshotUses($proposal->base_snapshot, $column, (int) $item->getKey())) {
                $usages[] = ['owner' => $item, 'context' => 'Änderungsvorschlag #'.$proposal->getKey()];
            }
        }

        return $usages;
    }

    /** @param array<string,mixed> $snapshot */
    private function snapshotUses(array $snapshot, string $column, int $id): bool
    {
        if ($column === 'council_term_id' && (int) ($snapshot['attributes'][$column] ?? 0) === $id) {
            return true;
        }
        foreach (['blocks', 'items', 'memberships', 'committeeMemberships'] as $collection) {
            foreach ($snapshot['collections'][$collection] ?? [] as $row) {
                if ((int) ($row[$column] ?? 0) === $id) {
                    return true;
                }
            }
        }

        return false;
    }

    public function guard(Model $item): void
    {
        if ($this->usages($item) !== []) {
            throw new DomainRuleViolation('Der Eintrag wird in Inhaltsbausteinen, Zuordnungen, Versionen oder Vorschlägen verwendet und kann nicht endgültig gelöscht werden.');
        }
    }
}
