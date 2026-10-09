<?php

namespace App\Services\Navigation;

use App\Enums\NavigationMenu;
use App\Exceptions\DomainRuleViolation;
use App\Models\NavigationItem;
use App\Models\PublicRoute;
use App\Rules\SafeUrl;
use App\Services\Audit\AuditLogger;
use App\Services\Content\RevisionService;
use App\Support\Content\NavigationNode;
use Illuminate\Support\Facades\DB;

final class NavigationManager
{
    public function validate(NavigationItem $item): void
    {
        $a = $item->getAttributes();
        $targets = (int) ! empty($a['public_route_id']) + (int) ! empty($a['external_resource_id']) + (int) ! empty($a['url']);
        if ($targets !== 1) {
            throw new DomainRuleViolation('Bitte genau ein Navigationsziel wählen.', 'public_route_id');
        }
        if (! empty($a['url']) && ! SafeUrl::isSafe($a['url'])) {
            throw new DomainRuleViolation('Ungültige externe Adresse.', 'url');
        }
        if (! empty($a['public_route_id']) && ! PublicRoute::query()->whereKey($a['public_route_id'])->where('is_canonical', true)->exists()) {
            throw new DomainRuleViolation('Das interne Ziel muss eine kanonische Adresse sein.', 'public_route_id');
        }
        $parentId = $a['parent_id'] ?? null;
        $visited = [];
        while ($parentId !== null) {
            if ($parentId == $item->getKey() || isset($visited[$parentId])) {
                throw new DomainRuleViolation('Diese Zuordnung erzeugt einen Kreis.', 'parent_id');
            }
            $visited[$parentId] = true;
            $parent = NavigationItem::query()->whereKey($parentId)->first();
            if ($parent === null || $parent->menu !== $item->menu) {
                throw new DomainRuleViolation('Übergeordnete Einträge müssen im selben Menü liegen.', 'parent_id');
            }
            $parentId = $parent->getAttribute('parent_id');
        }
        if ($item->exists && $item->children()->where('menu', '!=', $item->menu->value)->exists()) {
            throw new DomainRuleViolation('Bitte zuerst die untergeordneten Einträge in das gewünschte Menü verschieben.', 'menu');
        }
        $order = $a['sort_order'] ?? 0;
        if (! is_numeric($order) || $order < 0 || $order > 65535) {
            throw new DomainRuleViolation('Die Reihenfolge muss zwischen 0 und 65535 liegen.', 'sort_order');
        }
    }

    public function move(NavigationItem $item, ?NavigationItem $parent, int $order): void
    {
        DB::transaction(function () use ($item, $parent, $order) {
            NavigationItem::query()->orderBy('id')->lockForUpdate()->get();
            $item->refresh()->forceFill(['parent_id' => $parent?->getKey(), 'sort_order' => $order])->save();
            app(RevisionService::class)->record($item, null, 'Navigation verschoben');
            app(AuditLogger::class)->record('navigation.moved', $item, ['parent_id' => $parent?->getKey(), 'sort_order' => $order]);
        });
    }

    /**
     * Find a breadcrumb trail in the visible managed tree, without deriving URLs from hierarchy.
     *
     * @param  list<NavigationNode>  $nodes
     * @return list<NavigationNode>
     */
    public function trail(array $nodes, string $path): array
    {
        foreach ($nodes as $node) {
            if (rawurldecode((string) parse_url((string) $node->item->href(), PHP_URL_PATH)) === $path) {
                return [$node];
            }
            $children = $this->trail($node->children, $path);
            if ($children !== []) {
                return [$node, ...$children];
            }
        }

        return [];
    }

    /** @return list<NavigationNode> */
    public function tree(NavigationMenu $menu): array
    {
        $items = NavigationItem::query()->where('menu', $menu)->orderBy('sort_order')->orderBy('id')->get();
        $build = function (?int $parent, array $seen = []) use (&$build, $items): array {
            $nodes = [];
            foreach ($items as $item) {
                if ($item->getAttribute('parent_id') !== $parent || ! $item->getAttribute('is_active') || isset($seen[$item->getKey()]) || $item->href() === null) {
                    continue;
                }
                $nodes[] = new NavigationNode($item, $build((int) $item->getKey(), $seen + [$item->getKey() => true]));
            }

            return $nodes;
        };

        return $build(null);
    }
}
