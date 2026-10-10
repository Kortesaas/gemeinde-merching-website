<?php

namespace App\Services\Migration;

use App\Contracts\Routable;
use App\Exceptions\DomainRuleViolation;
use App\Models;
use App\Models\SourceReference;
use App\Services\Authorization\RoleSynchronizer;
use App\Services\Content\DocumentStorage;
use App\Services\Content\MediaStorage;
use App\Services\Content\RowDefinitions;
use App\Services\Routing\RouteManager;
use App\Services\Search\SearchIndexer;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Local-only importer of the public, reviewed preparation projection, never raw SQL. */
final class PublicContentImporter
{
    public const SOURCE = 'wordpress-public-migration';

    /** @var array<string,Model> */
    private array $models = [];

    /** @var list<array<string,mixed>> */
    private array $review = [];

    /** @var array<string,string> */
    private array $legacyTargets = [];

    /** @var list<string> */
    private array $written = [];

    /**
     * @param  array<string,mixed>  $manifest
     * @return array<string,mixed>
     */
    public function run(array $manifest): array
    {
        if (! app()->environment(['local', 'testing']) || ! str_ends_with((string) DB::connection()->getDatabaseName(), '_migration')) {
            throw new RuntimeException('Import requires a separate local *_migration database.');
        }
        if (SourceReference::query()->where('source_system', 'development-demo')->exists()) {
            throw new RuntimeException('Refusing to import into the demo database.');
        }
        if (($manifest['version'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported public manifest.');
        }
        $this->models = [];
        $this->written = [];
        app(RoleSynchronizer::class)->sync();
        $this->review = $manifest['review'];
        $this->legacyTargets = array_column($manifest['legacy'], 'key', 'url');
        try {
            DB::transaction(function () use ($manifest) {
                foreach ($manifest['assets'] as $record) {
                    try {
                        $this->asset($record);
                    } catch (DomainRuleViolation $e) {
                        $this->review[] = ['source' => $record['key'], 'status' => 'missing source', 'reason' => $e->getMessage()];
                    }
                }
                foreach ($manifest['records'] as $record) {
                    $model = $this->record($record);
                    if ($model instanceof Routable && $record['path'] !== null) {
                        app(RouteManager::class)->assign($model, $record['path']);
                        $model->unsetRelation('canonicalRoute');
                    }
                }
                foreach ($manifest['records'] as $record) {
                    $model = $this->models[$record['key']];
                    if (method_exists($model, 'blocks')) {
                        $model->blocks()->delete();
                        foreach ($record['blocks'] as $index => $block) {
                            $row = array_intersect_key($block, array_flip(Models\ContentBlock::COLUMNS));
                            $row['sort_order'] = $index;
                            if (isset($block['reference'])) {
                                $target = $this->models[$block['reference']] ?? null;
                                if ($target === null) {
                                    $this->review[] = ['source' => $record['key'], 'status' => 'missing source', 'reason' => 'Referenced asset failed import: '.$block['reference']];

                                    continue;
                                }
                                $column = ['image' => 'media_id', 'gallery' => 'gallery_id', 'downloads' => 'document_id', 'contact' => 'person_id', 'department' => 'department_id', 'services' => 'service_id', 'events' => 'event_id', 'external' => 'external_resource_id'][$block['type']];
                                $row[$column] = $target->getKey();
                            }
                            $row = $this->rewriteLinks($row);
                            try {
                                app(RowDefinitions::class)->validate('blocks', $row);
                            } catch (DomainRuleViolation $e) {
                                throw new RuntimeException('Invalid block on '.$record['key'].' referencing '.($block['reference'] ?? 'text').': '.$e->getMessage(), previous: $e);
                            }
                            Models\ContentBlock::withoutEvents(fn () => $model->blocks()->create($row));
                        }
                    }
                    foreach ($record['relations'] as $relation => $keys) {
                        $ids = [];
                        foreach (array_unique($keys) as $i => $key) {
                            if (isset($this->models[$key])) {
                                $ids[$this->models[$key]->getKey()] = ['sort_order' => $i];
                            }
                        }
                        $model->$relation()->sync($ids);
                    }
                    if ($model instanceof Models\Gallery) {
                        $model->items()->delete();
                        foreach ($record['items'] as $index => $key) {
                            if (isset($this->models[$key])) {
                                $model->items()->create(['media_id' => $this->models[$key]->getKey(), 'sort_order' => $index]);
                            }
                        }
                    }
                    if ($model instanceof Models\CouncilMember && isset($record['portrait'])) {
                        $model->update(['portrait_id' => ($this->models[$record['portrait']] ?? null)?->getKey()]);
                    }
                    if ($model instanceof Models\CouncilTerm) {
                        $model->memberships()->delete();
                        foreach ($record['memberships'] as $row) {
                            $model->memberships()->create(['council_member_id' => $this->models[$row['key']]->getKey(), 'role' => $row['role'], 'grouping' => $row['grouping'], 'sort_order' => $row['sort_order']]);
                        }
                    }
                    if ($model instanceof Models\Committee) {
                        $model->update(['council_term_id' => $this->models[$record['term']]->getKey()]);
                    }
                    if ($model instanceof Models\BudgetPlan) {
                        $this->budget($model, $record);
                    }
                }
                foreach ($manifest['legacy'] as $legacy) {
                    $model = $this->models[$legacy['key']] ?? null;
                    if ($model === null) {
                        $this->review[] = ['source' => $legacy['url'], 'status' => 'unresolved URL', 'reason' => 'Target did not import'];

                        continue;
                    }
                    Models\LegacyUrl::query()->updateOrCreate(['url_hash' => hash('sha256', $legacy['url'])], ['url' => $legacy['url'], 'target_type' => $model->getMorphClass(), 'target_id' => $model->getKey(), 'destination' => $legacy['destination']]);
                }
                $this->settings($manifest['contact_routes'] ?? []);
                $this->navigation();
            });
        } catch (\Throwable $error) {
            foreach ($this->written as $path) {
                Storage::disk((string) config('uploads.disk'))->delete($path);
            }
            throw $error;
        }
        $indexed = app(SearchIndexer::class)->rebuild();
        $counts = [];
        foreach ($this->models as $model) {
            $counts[$model->getMorphClass()] = ($counts[$model->getMorphClass()] ?? 0) + 1;
        }
        ksort($counts);
        $report = ['database' => DB::connection()->getDatabaseName(), 'counts' => $counts, 'search_entries' => $indexed, 'legacy_urls' => Models\LegacyUrl::query()->count(), 'review' => $this->review];
        $directory = base_path('docs/migration/local');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($directory.'/import-result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        $handle = fopen($directory.'/url-mappings.csv', 'w');
        if ($handle !== false) {
            fputcsv($handle, ['old_url', 'type', 'id', 'destination'], escape: '');
            foreach (Models\LegacyUrl::query()->with('target')->get() as $legacy) {
                $target = $legacy->target;
                if ($target instanceof Routable) {
                    $target->loadMissing('canonicalRoute');
                }
                fputcsv($handle, [$legacy->getAttribute('url'), $legacy->getAttribute('target_type'), $legacy->getAttribute('target_id'), $legacy->getAttribute('destination') ?? ($target instanceof Models\Document ? $target->downloadPath() : ($target instanceof Models\Media ? '/medien/'.$target->getKey() : ($target instanceof Routable ? $target->publicPath() : null)))], escape: '');
            }
            fclose($handle);
        }

        return $report;
    }

    /** @param array<string,mixed> $record */
    private function record(array $record): Model
    {
        $class = MorphMap::MAP[$record['type']] ?? throw new RuntimeException('Unknown type');
        if (! in_array($record['type'], ['page', 'article', 'notice', 'service', 'event', 'gallery', 'wahlperioden', 'ratsmitglieder', 'ausschuesse', 'person', 'department', 'organization', 'external-resource', 'budget-plan', 'location'], true)) {
            throw new RuntimeException('Nonpublic content type refused.');
        }
        $reference = SourceReference::query()->where('source_system', self::SOURCE)->where('source_id', $record['key'])->first();
        $model = $reference === null ? new $class : ($reference->referenceable ?? new $class);
        $model->fill($record['attributes']);
        if ($model instanceof Models\Committee) {
            $model->setAttribute('council_term_id', $this->models[$record['term']]->getKey());
        }
        if (method_exists($model, 'isVisible')) {
            $model->forceFill(['status' => 'published', 'publish_at' => CarbonImmutable::parse($record['date'], 'UTC'), 'expires_at' => null]);
        }
        if ($model instanceof Models\Article) {
            $categories = $record['categories'] ?? [];
            if ($categories !== []) {
                $category = Models\Category::query()->firstOrCreate(['context' => 'article', 'name' => $categories[0]], ['slug' => Str::slug($categories[0])]);
                $model->setAttribute('category_id', $category->getKey());
            }
        }
        $model->save();
        if ($model instanceof Models\Article) {
            $tags = [];
            foreach ($record['tags'] ?? [] as $name) {
                $tags[] = Models\Tag::query()->firstOrCreate(['name' => $name], ['slug' => Str::slug($name)])->getKey();
            }
            $model->tags()->sync($tags);
        }
        $this->reference($model, $record);
        $this->models[$record['key']] = $model;
        foreach ($record['source_keys'] ?? [] as $key) {
            $this->reference($model, ['key' => $key, 'urls' => []]);
        }

        return $model;
    }

    /** @param array<string,mixed> $record */
    private function asset(array $record): void
    {
        $path = realpath(base_path($record['file']));
        $prepared = realpath(base_path('migration-source/prepared'));
        $recovered = realpath(base_path('migration-source/recovery'));
        if ($path === false || ! (($prepared !== false && str_starts_with($path, $prepared.'/')) || ($recovered !== false && str_starts_with($path, $recovered.'/'))) || ! hash_equals($record['sha256'], (string) hash_file('sha256', $path))) {
            throw new RuntimeException('Invalid or changed prepared file.');
        }
        $reference = SourceReference::query()->where('source_system', self::SOURCE)->where('source_id', $record['key'])->first();
        $model = $reference === null ? ($record['type'] === 'media' ? new Models\Media : new Models\Document) : $reference->referenceable;
        if ($model === null) {
            throw new RuntimeException('Source reference target is missing.');
        }
        $model->fill($record['attributes']);
        if (! $model->exists) {
            $extension = strtolower(pathinfo($record['original_filename'], PATHINFO_EXTENSION));
            $file = new UploadedFile($path, $record['original_filename'], test: true);
            if ($model instanceof Models\Media) {
                if ($extension === 'gif') {
                    $image = imagecreatefromgif($path);
                    if ($image === false) {
                        throw new RuntimeException('Invalid legacy GIF');
                    }
                    $safePath = base_path('migration-source/prepared/safe-'.hash('sha256', $record['key']).'.png');
                    imagepng($image, $safePath);
                    $file = new UploadedFile($safePath, pathinfo($record['original_filename'], PATHINFO_FILENAME).'.png', test: true);
                }
                // The verified gallery originals include 24-megapixel camera images.
                // Keep the normal upload policy unchanged outside this local source import.
                $pixelLimit = config('uploads.max_image_pixels');
                config(['uploads.max_image_pixels' => max((int) $pixelLimit, 24000000)]);
                try {
                    app(MediaStorage::class)->attach($model, $file);
                } finally {
                    config(['uploads.max_image_pixels' => $pixelLimit]);
                }
            } elseif ($model instanceof Models\Document) {
                if (in_array($extension, ['doc', 'zip'], true)) {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
                    $allowed = $extension === 'doc' ? ['application/msword', 'application/CDFV2'] : ['application/zip'];
                    if (! in_array($mime, $allowed, true) || filesize($path) > 20 * 1024 * 1024) {
                        throw new RuntimeException('Unsafe legacy document');
                    }
                    $storagePath = 'migration-originals/'.$record['sha256'].'.'.$extension;
                    Storage::disk((string) config('uploads.disk'))->put($storagePath, (string) file_get_contents($path));
                    $model->forceFill(['file_path' => $storagePath, 'original_filename' => $record['original_filename'], 'mime_type' => $mime, 'extension' => $extension, 'size_bytes' => filesize($path), 'sha256' => $record['sha256']]);
                } else {
                    app(DocumentStorage::class)->attach($model, $file);
                }
            }
        }
        if (! $model->exists) {
            $this->written[] = (string) $model->getAttribute('file_path');
        }
        // Delivery can be PNG while the original source filename remains a GIF.
        if ($model instanceof Models\Media && strtolower(pathinfo($record['original_filename'], PATHINFO_EXTENSION)) === 'gif') {
            $model->forceFill(['original_filename' => $record['original_filename']]);
        }
        $model->forceFill(['status' => 'published', 'publish_at' => CarbonImmutable::parse($record['date'], 'UTC')])->save();
        $this->reference($model, $record);
        $this->models[$record['key']] = $model;
        foreach (array_unique($record['source_keys'] ?? []) as $key) {
            $this->reference($model, ['key' => $key, 'urls' => $record['urls']]);
        }
    }

    /** @param array<string,mixed> $record */
    private function reference(Model $model, array $record): void
    {
        $url = is_array($record['urls']) ? ($record['urls'][0] ?? null) : null;
        if (! SourceReference::query()->where('source_system', self::SOURCE)->where('source_id', $record['key'])->where('referenceable_type', $model->getMorphClass())->exists()) {
            (new SourceReference)->forceFill(['source_system' => self::SOURCE, 'source_id' => $record['key'], 'referenceable_type' => $model->getMorphClass(), 'referenceable_id' => $model->getKey(), 'original_url' => $url, 'imported_at' => now()])->save();
        }
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function rewriteLinks(array $row): array
    {
        if (! isset($row['text'])) {
            return $row;
        }
        $row['text'] = preg_replace_callback('/\]\((https?:\/\/(?:www\.)?gemeinde-merching\.de\/[^\s)]*)\)/u', function ($match) {
            $url = html_entity_decode($match[1]);
            $path = rtrim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/') ?: '/';
            $query = parse_url($url, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $parts);
                foreach (['wpdmdl', 'p', 'page_id', 'wpfb_dl', 'attachment_id'] as $key) {
                    if (isset($parts[$key]) && is_string($parts[$key]) && ctype_digit($parts[$key])) {
                        $path .= '?'.$key.'='.(int) $parts[$key];
                        break;
                    }
                }
            }
            $model = $this->models[$this->legacyTargets[$path] ?? ''] ?? null;
            if ($model instanceof Models\Document) {
                return ']('.$model->downloadPath().')';
            }
            if ($model instanceof Models\Media) {
                return '](/medien/'.$model->getKey().')';
            }
            if ($model instanceof Routable && $model->publicPath() !== null) {
                return ']('.$model->publicPath().')';
            }

            // Keep local aliases; compatibility middleware performs publication checks at delivery.
            return ']('.$path.')';
        }, (string) $row['text']);

        return $row;
    }

    /** @param array<string,mixed> $record */
    private function budget(Models\BudgetPlan $plan, array $record): void
    {
        if ($plan->publications()->exists()) {
            return;
        }
        $manifest = [];
        foreach ($record['components'] as $index => $key) {
            $document = $this->models[$key] ?? null;
            if (! $document instanceof Models\Document) {
                throw new RuntimeException('Budget original missing: '.$key);
            }
            $path = 'migration-budgets/'.$plan->getKey().'/'.$document->sha256.'.pdf';
            $disk = Storage::disk((string) config('uploads.disk'));
            $disk->put($path, (string) $disk->get($document->file_path));
            $this->written[] = $path;
            $source = new Models\BudgetSource;
            $source->forceFill(['budget_plan_id' => $plan->getKey(), 'file_path' => $path, 'original_filename' => $document->original_filename, 'size_bytes' => $document->size_bytes, 'sha256' => $document->sha256])->save();
            $plan->components()->create(['budget_source_id' => $source->getKey(), 'sort_order' => $index]);
            $manifest[] = ['id' => $source->getKey(), 'original_filename' => $source->getAttribute('original_filename'), 'size_bytes' => $source->getAttribute('size_bytes'), 'sha256' => $source->getAttribute('sha256')];
        }
        $plan->forceFill(['generation_status' => 'failed', 'generation_error' => 'FPDI cannot parse the main source PDF; originals published without merged derivative.'])->save();
        $receipt = new Models\BudgetPublication;
        $receipt->forceFill(['budget_plan_id' => $plan->getKey(), 'budget_generation_id' => null, 'year' => $plan->getAttribute('year'), 'topic' => $plan->getAttribute('topic'), 'title' => $plan->getAttribute('title'), 'status' => 'published', 'publish_at' => $plan->publish_at, 'expires_at' => null, 'publisher_name' => 'Lokale Inhaltsmigration', 'public_url' => $plan->publicPath(), 'accessibility_status' => 'not_checked', 'show_components' => true, 'source_only' => true, 'source_manifest' => $manifest])->save();
    }

    /** @param list<array{label:string,recipients:list<string>,sort_order:int}> $contactRoutes */
    private function settings(array $contactRoutes): void
    {
        $location = $this->record(['key' => 'structure:town-hall', 'type' => 'location', 'attributes' => ['name' => 'Rathaus Merching', 'type' => 'verwaltung', 'street' => 'Hauptstr. 26', 'postal_code' => '86504', 'city' => 'Merching', 'phone' => '(0 82 33) 74 41 - 0', 'opening_hours' => "Mo, Di, Do, Fr: 08.00–12.00 Uhr\nDo: 14.00–18.00 Uhr\nMittwoch geschlossen", 'is_active' => true], 'path' => null, 'date' => '2026-10-10 00:00:00', 'urls' => ['https://www.gemeinde-merching.de/adressen-oeffnungszeiten/']]);
        $department = $this->record(['key' => 'structure:central', 'type' => 'department', 'attributes' => ['name' => 'Gemeindeverwaltung Merching', 'phone' => '(0 82 33) 74 41 - 0', 'location_id' => $location->getKey(), 'opening_hours' => $location->getAttribute('opening_hours'), 'is_active' => true], 'path' => null, 'date' => '2026-10-10 00:00:00', 'urls' => ['https://www.gemeinde-merching.de/adressen-oeffnungszeiten/']]);
        // User-requested aliases from the active legacy form; recipients remain encrypted
        // and never enter public models, reports or search. Local delivery uses Mailpit.
        $contactRoutes = $contactRoutes ?: [['label' => 'Gemeindeverwaltung', 'recipients' => ['rathaus@gemeinde-merching.bayern.de'], 'sort_order' => 0]];
        $contact = Models\ContactRoute::query()->where('label', $contactRoutes[0]['label'])->first()
            ?? Models\ContactRoute::query()->where('label', 'Gemeindeverwaltung')->first()
            ?? new Models\ContactRoute;
        foreach ($contactRoutes as $index => $definition) {
            $topic = $index === 0 ? $contact : (Models\ContactRoute::query()->where('label', $definition['label'])->first() ?? new Models\ContactRoute);
            $topic->fill([...$definition, 'is_active' => true, 'department_id' => $index === 0 ? $department->getKey() : null])->save();
        }
        $hero = collect($this->models)->first(fn ($m, $key) => $m instanceof Models\Media && str_contains($key, 'Rathaus-2020-neu'));
        $greeting = $this->models['editorial:homepage-mayor'] ?? collect($this->models)->first(fn ($m, $key) => $m instanceof Models\Media && str_contains($key, 'Helmut-Luichtl-kl.'));
        (Models\SiteSettings::query()->find(1) ?? new Models\SiteSettings)->fill(['municipality_name' => 'Gemeinde Merching', 'town_hall_location_id' => $location->getKey(), 'central_department_id' => $department->getKey(), 'central_contact_route_id' => $contact->getKey(), 'homepage_media_id' => $hero?->getKey(), 'greeting_media_id' => $greeting?->getKey(), 'greeting_page_id' => $this->models['wp:92']->getKey(), 'greeting_text' => "Liebe Mitbürgerinnen, liebe Mitbürger, liebe Besucher,\n\nich freue mich sehr, dass sie uns auf dem digitalen Weg besuchen.\n\nAuf unserer Internetseite finden Sie viel Wissenswertes und Interessantes über Merching.\n\nEs stehen Ihnen aber auch aktuelle Informationen zu verschiedenen Themen rund um unsere Gemeinde zur Verfügung.", 'greeting_name' => 'Helmut Luichtl', 'greeting_role' => '1. Bürgermeister', 'postal_address' => "Hauptstr. 26\n86504 Merching", 'default_meta_description' => 'Informationen und Bürgerservice der Gemeinde Merching im Landkreis Aichach-Friedberg.'])->save();
    }

    private function navigation(): void
    {
        // Only this isolated migration database is rebuilt; stable keys make reruns idempotent.
        DB::table('navigation_items')->delete();
        $shortLabels = ['wp:20' => 'Verwaltung', 'wp:92' => 'Grußwort'];
        $groups = [
            ['structure:rathaus', 'Rathaus & Politik', ['wp:20', 'wp:212', 'wp:92', 'wp:227', 'wp:9222']],
            ['wp:10', 'Bürgerservice', ['wp:222', 'wp:224', 'wp:257', 'wp:7085', 'wp:2847', 'wp:1999', 'wp:441', 'wp:353']],
            ['structure:leben', 'Leben & Freizeit', ['wp:6', 'wp:14', 'wp:2316', 'wp:249', 'wp:251', 'wp:255', 'wp:547']],
            ['structure:bauen', 'Bauen & Wirtschaft', ['wp:12', 'wp:4787', 'wp:415']],
            ['wp:8', 'Aktuelles', ['wp:4', 'wp:670', 'wp:542', 'wp:4631', 'wp:4765']],
        ];
        foreach ($groups as $i => [$key, $label, $children]) {
            $model = $this->routable($key);
            $parent = Models\NavigationItem::query()->create(['menu' => 'main', 'label' => $label, 'public_route_id' => $model->canonicalRoute()->firstOrFail()->getKey(), 'sort_order' => $i, 'is_active' => true]);
            foreach ($children as $j => $child) {
                $target = $this->routable($child);
                Models\NavigationItem::query()->create(['menu' => 'main', 'parent_id' => $parent->getKey(), 'label' => $shortLabels[$child] ?? $target->displayTitle(), 'public_route_id' => $target->canonicalRoute()->firstOrFail()->getKey(), 'sort_order' => $j, 'is_active' => true]);
            }
        }
        foreach (['wp:222', 'wp:257', 'wp:20', 'wp:7085', 'wp:2847'] as $i => $key) {
            $model = $this->routable($key);
            Models\NavigationItem::query()->create(['menu' => 'service', 'label' => $key === 'wp:20' ? 'Öffnungszeiten' : $model->displayTitle(), 'public_route_id' => $model->canonicalRoute()->firstOrFail()->getKey(), 'sort_order' => $i, 'is_active' => true]);
        }
        $footer = [
            ['Bürgerservice', 'wp:10', [['Leistungen A–Z', 'wp:222'], ['Formulare', 'wp:257'], ['Dokumente', 'wp:7085'], ['Verwaltung und Öffnungszeiten', 'wp:20']]],
            ['Aktuelles', 'wp:8', [['Meldungen', 'wp:8'], ['Veranstaltungen', 'wp:4'], ['Bekanntmachungen', 'wp:670']]],
            ['Rechtliches', 'wp:24', [['Impressum', 'wp:24'], ['Datenschutz', 'wp:3658'], ['Barrierefreiheit', 'wp:9637'], ['Inhaltsverzeichnis', 'wp:26']]],
        ];
        foreach ($footer as $i => [$label, $key, $children]) {
            $model = $this->routable($key);
            $parent = Models\NavigationItem::query()->create(['menu' => 'footer', 'label' => $label, 'public_route_id' => $model->canonicalRoute()->firstOrFail()->getKey(), 'sort_order' => $i, 'is_active' => true]);
            foreach ($children as $j => [$childLabel, $childKey]) {
                $target = $this->routable($childKey);
                Models\NavigationItem::query()->create(['menu' => 'footer', 'parent_id' => $parent->getKey(), 'label' => $childLabel, 'public_route_id' => $target->canonicalRoute()->firstOrFail()->getKey(), 'sort_order' => $j, 'is_active' => true]);
            }
        }
    }

    private function routable(string $key): Model&Routable
    {
        $model = $this->models[$key];
        if (! $model instanceof Routable) {
            throw new RuntimeException('Navigation target is not routable.');
        }

        return $model;
    }
}
