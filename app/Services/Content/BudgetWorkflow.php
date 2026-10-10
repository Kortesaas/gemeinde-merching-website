<?php

namespace App\Services\Content;

use App\Enums\AccessibilityStatus;
use App\Enums\PublicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\BudgetGeneration;
use App\Models\BudgetPlan;
use App\Models\BudgetPublication;
use App\Models\BudgetSource;
use App\Models\Document;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/** Publication boundary shared by direct edits, approved proposals and revision restores. */
class BudgetWorkflow
{
    /** @var list<string> */
    private array $written = [];

    private int $transactionDepth = 0;

    /** Compensate private file writes if the surrounding database transaction fails.
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $start = count($this->written);
        $this->transactionDepth++;
        $failed = false;
        try {
            return DB::transaction(fn () => $callback());
        } catch (Throwable $e) {
            $failed = true;
            foreach (array_slice($this->written, $start) as $path) {
                Storage::disk((string) config('uploads.disk'))->delete($path);
            }
            throw $e;
        } finally {
            $this->transactionDepth--;
            if ($failed || $this->transactionDepth === 0) {
                $this->written = array_slice($this->written, 0, $start);
            }
        }
    }

    /** Serialize changes to a package before reading/editing its associations. */
    public function lock(Model $model): void
    {
        if ($model instanceof BudgetPlan && $model->exists) {
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $model->refresh();
        }
    }

    public function upload(BudgetPlan $plan, UploadedFile $file, User $actor): BudgetSource
    {
        $document = new Document;
        try {
            app(DocumentStorage::class)->attach($document, $file);
        } catch (DomainRuleViolation $e) {
            throw new DomainRuleViolation($e->getMessage(), 'files');
        }
        $this->written[] = $document->file_path;
        if ($document->mime_type !== 'application/pdf' || $document->extension !== 'pdf') {
            throw new DomainRuleViolation('Bitte ausschließlich PDF-Dateien hochladen.', 'files');
        }
        if (! app(DocumentStorage::class)->exists($document)) {
            throw new DomainRuleViolation('Die PDF-Datei konnte nicht gespeichert werden. Bitte erneut hochladen.', 'files');
        }
        $source = new BudgetSource;
        $source->forceFill([
            'budget_plan_id' => $plan->getKey(), 'file_path' => $document->file_path,
            'original_filename' => $document->original_filename, 'size_bytes' => $document->size_bytes,
            'sha256' => $document->sha256, 'uploaded_by' => $actor->getKey(),
        ])->save();
        app(AuditLogger::class)->record('budget.source_uploaded', $plan, ['source' => $source->getKey(), 'sha256' => $source->sha256, 'filename' => $source->original_filename], actor: $actor);

        return $source;
    }

    public function fingerprint(BudgetPlan $plan): string
    {
        $ids = $plan->components()->pluck('budget_source_id')->all();

        // IDs identify immutable source bytes; order is significant. Year affects download filename.
        return hash('sha256', json_encode([$plan->year, $ids], JSON_THROW_ON_ERROR));
    }

    public function finish(Model $model, User $actor, bool $publicationChanged = false): void
    {
        if (! $model instanceof BudgetPlan) {
            return;
        }
        $this->markStale($model);
        if ($model->isPublicationLocked()) {
            $generation = $this->generate($model, $actor);
            if ($model->status === PublicationStatus::Published) {
                $this->receipt($model, $generation, $actor, $publicationChanged);
            }
        }
    }

    public function markStale(BudgetPlan $plan): void
    {
        $generation = $plan->currentGeneration()->first();
        if ($generation === null || $generation->fingerprint !== $this->fingerprint($plan)) {
            $plan->forceFill(['generation_status' => 'stale', 'generation_error' => null, 'accessibility_status' => AccessibilityStatus::NotChecked, 'accessibility_notes' => null])->save();
        }
    }

    public function generate(BudgetPlan $plan, User $actor): BudgetGeneration
    {
        try {
            return $this->build($plan, $actor);
        } catch (DomainRuleViolation $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw new DomainRuleViolation('Das Gesamt-PDF konnte nicht erstellt oder gespeichert werden. Bitte erneut versuchen. Die Veröffentlichung wurde nicht übernommen.', 'components');
        }
    }

    private function build(BudgetPlan $plan, User $actor): BudgetGeneration
    {
        $disk = Storage::disk((string) config('uploads.disk'));
        $fingerprint = $this->fingerprint($plan);
        $rows = $plan->components()->with('source')->get();
        foreach ($rows as $row) {
            $source = $row->source;
            if ($source === null || (int) $source->budget_plan_id !== (int) $plan->getKey()) {
                throw new DomainRuleViolation('Eine Quelldatei fehlt oder gehört zu einem anderen Haushaltsplan.', 'components');
            }
            $bytes = $disk->exists($source->file_path) ? $disk->get($source->file_path) : null;
            if ($bytes === null || strlen($bytes) !== $source->size_bytes || ! hash_equals($source->sha256, hash('sha256', $bytes))) {
                throw new DomainRuleViolation('Quelldatei „'.$source->original_filename.'“ fehlt oder wurde verändert. Bitte erneut hochladen.', 'components');
            }
        }
        unset($bytes);
        $current = $plan->currentGeneration()->first();
        if ($current !== null && $current->fingerprint === $fingerprint && $disk->exists($current->file_path)
            && hash_equals($current->sha256, hash('sha256', (string) $disk->get($current->file_path)))) {
            $plan->forceFill(['generation_status' => 'ready', 'generation_error' => null])->save();

            return $current;
        }
        if ($rows->isEmpty() || $rows->count() > (int) config('budgets.max_sources')) {
            throw new DomainRuleViolation('Bitte 1 bis '.config('budgets.max_sources').' PDF-Dateien auswählen und die Reihenfolge speichern.', 'components');
        }
        if ($rows->sum(fn ($row) => $row->source->size_bytes ?? 0) > (int) config('budgets.max_total_bytes')) {
            throw new DomainRuleViolation('Das PDF-Paket ist zu groß für die Zusammenstellung. Bitte die Quelldateien verkleinern.', 'components');
        }
        $pdf = new Fpdi;
        $pdf->SetTitle($plan->title, true);
        $pdf->SetCreator('Gemeinde Merching');
        $pdf->SetAutoPageBreak(false);
        $started = microtime(true);
        $manifest = [];
        $totalPages = 0;
        foreach ($rows as $index => $row) {
            $source = $row->source;
            if ($source === null) {
                throw new DomainRuleViolation('Eine Quelldatei fehlt.', 'components');
            }
            if ((int) $source->budget_plan_id !== (int) $plan->getKey()) {
                throw new DomainRuleViolation('Eine Quelldatei gehört zu einem anderen Haushaltsplan.', 'components');
            }
            if (! $disk->exists($source->file_path)) {
                throw new DomainRuleViolation('Quelldatei „'.$source->original_filename.'“ fehlt. Bitte erneut hochladen.', 'components');
            }
            $bytes = (string) $disk->get($source->file_path);
            if (strlen($bytes) !== $source->size_bytes || ! hash_equals($source->sha256, hash('sha256', $bytes))) {
                throw new DomainRuleViolation('Quelldatei „'.$source->original_filename.'“ stimmt nicht mit dem gespeicherten Upload überein. Bitte erneut hochladen.', 'components');
            }
            try {
                $pages = $pdf->setSourceFile(StreamReader::createByString($bytes));
                if ($pages < 1 || ($totalPages + $pages) > (int) config('budgets.max_pages')) {
                    throw new DomainRuleViolation('Das Paket hat keine Seiten oder überschreitet die Seitenbegrenzung.', 'components');
                }
                for ($page = 1; $page <= $pages; $page++) {
                    if (microtime(true) - $started > (int) config('budgets.max_seconds')) {
                        throw new DomainRuleViolation('Die Zusammenstellung dauert zu lange. Bitte kleinere PDF-Dateien verwenden.', 'components');
                    }
                    // Do not import active links/actions, forms or annotations from untrusted PDFs.
                    $template = $pdf->importPage($page);
                    $size = $pdf->getTemplateSize($template);
                    if (! is_array($size)) {
                        throw new \RuntimeException('Invalid page dimensions.');
                    }
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($template);
                }
            } catch (DomainRuleViolation $e) {
                throw $e;
            } catch (Throwable) {
                throw new DomainRuleViolation('PDF „'.$source->original_filename.'“ konnte nicht zusammengeführt werden. Bitte als unverschlüsselte PDF 1.4 ohne komprimierte Objektströme neu exportieren und erneut hochladen. Die Veröffentlichung wurde nicht übernommen.', 'components');
            }
            $totalPages += $pages;
            $source->forceFill(['page_count' => $pages])->save();
            $manifest[] = ['id' => $source->getKey(), 'position' => $index + 1, 'original_filename' => $source->original_filename, 'size_bytes' => $source->size_bytes, 'sha256' => $source->sha256, 'page_count' => $pages, 'uploaded_at' => $source->created_at->toIso8601String()];
        }
        $output = $pdf->Output('S');
        $path = 'uploads/budgets/'.Str::uuid().'.pdf';
        $this->written[] = $path;
        if (! $disk->put($path, $output)) {
            throw new DomainRuleViolation('Das Gesamt-PDF konnte nicht gespeichert werden. Bitte erneut versuchen.', 'components');
        }
        $generation = new BudgetGeneration;
        $generation->forceFill([
            'budget_plan_id' => $plan->getKey(), 'fingerprint' => $fingerprint, 'file_path' => $path,
            'original_filename' => 'Haushaltsplan-'.$plan->year.'.pdf', 'size_bytes' => strlen($output),
            'sha256' => hash('sha256', $output), 'page_count' => $totalPages, 'sources' => $manifest, 'generated_by' => $actor->getKey(),
        ])->save();
        $plan->forceFill(['current_generation_id' => $generation->getKey(), 'generation_status' => 'ready', 'generation_error' => null, 'accessibility_status' => AccessibilityStatus::NotChecked, 'accessibility_notes' => null])->save();
        $plan->unsetRelation('currentGeneration');
        app(AuditLogger::class)->record('budget.generated', $plan, ['generation' => $generation->getKey(), 'sha256' => $generation->sha256, 'pages' => $totalPages], actor: $actor);

        return $generation;
    }

    private function receipt(BudgetPlan $plan, BudgetGeneration $generation, User $actor, bool $force): void
    {
        $plan->unsetRelation('canonicalRoute');
        $data = [
            'budget_plan_id' => $plan->getKey(), 'budget_generation_id' => $generation->getKey(),
            'year' => $plan->year, 'title' => $plan->title, 'status' => $plan->status->value,
            'publish_at' => $plan->publish_at, 'expires_at' => $plan->expires_at,
            'public_url' => url((string) $plan->publicPath()), 'accessibility_status' => $plan->accessibility_status->value,
            'accessibility_notes' => $plan->accessibility_notes, 'show_components' => $plan->show_components,
        ];
        $last = $plan->publications()->first();
        // A repeated save without a publication change must not replace the original publisher.
        if (! $force && $last !== null && collect($data)->every(fn ($v, $k) => (string) $last->getRawOriginal($k) === (string) ($v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : (is_bool($v) ? (int) $v : $v)))) {
            return;
        }
        $receipt = new BudgetPublication;
        $receipt->forceFill($data + ['published_by' => $actor->getKey(), 'publisher_name' => $actor->name])->save();
        app(AuditLogger::class)->record('budget.publication_recorded', $plan, ['receipt' => $receipt->getKey(), 'generation' => $generation->getKey(), 'sha256' => $generation->sha256], actor: $actor);
    }

    /** Reuse the Document response's MIME, disposition, sandbox and streaming headers. */
    public function document(BudgetSource|BudgetGeneration $file): Document
    {
        return (new Document)->forceFill($file->only(['file_path', 'original_filename', 'size_bytes', 'sha256']) + ['mime_type' => 'application/pdf', 'extension' => 'pdf']);
    }
}
