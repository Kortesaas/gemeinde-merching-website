<?php

namespace App\Services\Routing;

use App\Exceptions\DomainRuleViolation;
use App\Models\PublicRoute;
use App\Models\Redirect;
use App\Rules\SafeUrl;
use App\Services\Audit\AuditLogger;
use App\Support\Routing\PublicPath;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates and updates redirects while preventing duplicate sources, loops,
 * chains and collisions with content routes.
 *
 * Chains are avoided in both directions: a redirect may not point to another
 * redirect (the editor is told the final target), and existing redirects that
 * point to the new source are re-pointed to the new destination ("flattened").
 */
class RedirectManager
{
    private const MAX_HOPS = 25;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{source_path: string, destination?: string|null, status_code: int, is_active?: bool, notes?: string|null}  $data
     *
     * @throws DomainRuleViolation
     */
    public function save(array $data, ?Redirect $redirect = null): Redirect
    {
        $source = $this->normalizeSource($data['source_path']);
        $status = (int) $data['status_code'];
        $active = (bool) ($data['is_active'] ?? true);
        $destination = $status === 410 ? null : $this->normalizeDestination($data['destination'] ?? null);

        if (! array_key_exists($status, Redirect::STATUS_CODES)) {
            throw new DomainRuleViolation('Ungültiger Statuscode.', 'status_code');
        }

        $this->assertNoConflicts($source['key'], $redirect);

        if ($active && $destination !== null && str_starts_with($destination, '/')) {
            $this->assertNoLoopOrChain($source['key'], PublicPath::key($destination), $redirect);
        }

        return DB::transaction(function () use ($redirect, $source, $destination, $status, $active, $data) {
            $isNew = $redirect === null;
            $redirect ??= new Redirect(['created_by' => auth()->id()]);
            $redirect->fill([
                'source_path' => $source['path'],
                'source_key' => $source['key'],
                'destination' => $destination,
                'status_code' => $status,
                'is_active' => $active,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->audit->record($isNew ? 'redirect.created' : 'redirect.updated', $redirect, [
                'source' => $redirect->source_path, 'destination' => $redirect->destination, 'status' => $status, 'active' => $active,
            ]);

            if ($active && $destination !== null) {
                $this->flattenIncoming($redirect);
            }

            return $redirect;
        });
    }

    public function delete(Redirect $redirect): void
    {
        $redirect->delete();
        $this->audit->record('redirect.deleted', $redirect, ['source' => $redirect->source_path]);
    }

    /**
     * @return array{path: string, key: string}
     */
    private function normalizeSource(string $raw): array
    {
        try {
            $source = PublicPath::normalize($raw);
        } catch (InvalidArgumentException $e) {
            throw new DomainRuleViolation($e->getMessage(), 'source_path');
        }

        if (PublicPath::isReserved($source['key'])) {
            throw new DomainRuleViolation('Diese Adresse ist für das System reserviert.', 'source_path');
        }

        return $source;
    }

    private function normalizeDestination(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            throw new DomainRuleViolation('Bitte geben Sie ein Ziel an (Pfad wie /aktuelles oder https://…).', 'destination');
        }

        if (str_starts_with($raw, '/')) {
            try {
                return PublicPath::normalize($raw)['path'];
            } catch (InvalidArgumentException $e) {
                throw new DomainRuleViolation($e->getMessage(), 'destination');
            }
        }

        if (! SafeUrl::isSafe($raw)) {
            throw new DomainRuleViolation('Das Ziel muss ein Pfad (/…) oder eine vollständige https://-Adresse sein.', 'destination');
        }

        return $raw;
    }

    private function assertNoConflicts(string $sourceKey, ?Redirect $current): void
    {
        $duplicate = Redirect::query()->where('source_key', $sourceKey)
            ->when($current !== null, fn ($q) => $q->whereKeyNot($current?->getKey()))->exists();
        if ($duplicate) {
            throw new DomainRuleViolation('Für diese Adresse gibt es bereits eine Weiterleitung.', 'source_path');
        }

        if (PublicRoute::query()->where('path_key', $sourceKey)->where('is_active', true)->exists()) {
            throw new DomainRuleViolation('Unter dieser Adresse ist bereits ein Inhalt erreichbar. Inhalte haben Vorrang vor Weiterleitungen.', 'source_path');
        }
    }

    private function assertNoLoopOrChain(string $sourceKey, string $destinationKey, ?Redirect $current): void
    {
        if ($destinationKey === $sourceKey) {
            throw new DomainRuleViolation('Quelle und Ziel dürfen nicht identisch sein.', 'destination');
        }

        // Former path of a content record: point directly to its current address.
        $alias = PublicRoute::query()->where('path_key', $destinationKey)->where('is_canonical', false)->first();
        if ($alias !== null) {
            $canonical = PublicRoute::query()->where('routable_type', $alias->routable_type)
                ->where('routable_id', $alias->routable_id)->where('is_canonical', true)->value('path');
            throw new DomainRuleViolation("Das Ziel ist eine frühere Adresse. Bitte verwenden Sie die aktuelle Adresse: {$canonical}", 'destination');
        }

        $next = $this->activeRedirect($destinationKey, $current);
        if ($next !== null) {
            // Would the target lead back to the source?
            $key = $destinationKey;
            for ($hops = 0; $hops < self::MAX_HOPS && ($hop = $this->activeRedirect($key, $current)) !== null; $hops++) {
                if ($hop->destination === null || ! str_starts_with($hop->destination, '/')) {
                    break;
                }
                $key = PublicPath::key($hop->destination);
                if ($key === $sourceKey) {
                    throw new DomainRuleViolation('Diese Weiterleitung würde eine Schleife erzeugen.', 'destination');
                }
            }

            throw new DomainRuleViolation("Das Ziel ist selbst eine Weiterleitung (nach {$next->destination}). Bitte direkt auf das endgültige Ziel verweisen.", 'destination');
        }
    }

    private function activeRedirect(string $sourceKey, ?Redirect $except): ?Redirect
    {
        return Redirect::query()->where('source_key', $sourceKey)->where('is_active', true)
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except?->getKey()))->first();
    }

    /**
     * Re-point redirects that target the source of $redirect to its destination.
     */
    private function flattenIncoming(Redirect $redirect): void
    {
        $incoming = Redirect::query()->whereKeyNot($redirect->getKey())->where('is_active', true)
            ->whereNotNull('destination')->where('destination', 'like', '/%')->get()
            ->filter(fn (Redirect $r) => PublicPath::key((string) $r->destination) === $redirect->source_key);

        foreach ($incoming as $other) {
            if (str_starts_with((string) $redirect->destination, '/') && PublicPath::key((string) $redirect->destination) === $other->source_key) {
                continue; // would become a self-redirect; loop check prevents this case
            }

            $other->update(['destination' => $redirect->destination, 'status_code' => $redirect->status_code]);
            $this->audit->record('redirect.flattened', $other, ['source' => $other->source_path, 'destination' => $other->destination]);
        }
    }
}
