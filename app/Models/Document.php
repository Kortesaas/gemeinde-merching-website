<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Enums\AccessibilityStatus;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasPublicRoute;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSourceReferences;
use App\Models\Concerns\TracksEditors;
use App\Support\Search\SearchDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Central document/download record: one uploaded file, placed wherever it is
 * needed (Ortsrecht, Haushaltspläne, Formulare, Bekanntmachungen, …).
 *
 * File columns are system data set only by App\Services\Content\DocumentStorage
 * (never mass-assignable, not part of revisions).
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string $sha256
 * @property int|null $year
 * @property string $language
 * @property AccessibilityStatus $accessibility_status
 * @property int|null $accessible_alternative_id
 * @property int|null $replaces_document_id
 */
#[Fillable(['title', 'description', 'category_id', 'year', 'document_date', 'valid_from', 'valid_until', 'language', 'accessibility_status', 'accessibility_notes', 'accessible_alternative_id', 'replaces_document_id'])]
class Document extends Model implements Revisionable, Routable, Searchable
{
    use HasPublication, HasPublicRoute, HasRevisions, HasSourceReferences, SoftDeletes, TracksEditors;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'accessibility_status' => 'not_checked',
        'language' => 'de',
    ];

    protected function casts(): array
    {
        return [
            'accessibility_status' => AccessibilityStatus::class,
            'year' => 'integer',
            'size_bytes' => 'integer',
            'document_date' => 'immutable_date',
            'valid_from' => 'immutable_date',
            'valid_until' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'replaces_document_id')->withTrashed();
    }

    /**
     * @return HasOne<Document, $this>
     */
    public function replacedBy(): HasOne
    {
        return $this->hasOne(Document::class, 'replaces_document_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function accessibleAlternative(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'accessible_alternative_id')->withTrashed();
    }

    /**
     * Documents for which this one is the accessible alternative.
     *
     * @return HasMany<Document, $this>
     */
    public function alternativeFor(): HasMany
    {
        return $this->hasMany(Document::class, 'accessible_alternative_id')->withTrashed();
    }

    /**
     * Owners that place this document, including owners in the recycle bin
     * ("Wo wird diese Datei verwendet?").
     *
     * @return array<string, BelongsToMany<Model, $this>>
     */
    public function placementRelations(): array
    {
        return [
            'articles' => $this->placedOn(Article::class),
            'events' => $this->placedOn(Event::class),
            'publicNotices' => $this->placedOn(PublicNotice::class),
            'services' => $this->placedOn(Service::class),
            'lifeSituations' => $this->placedOn(LifeSituation::class),
            'pages' => $this->placedOn(Page::class),
        ];
    }

    /**
     * @param  class-string<Model>  $owner
     * @return BelongsToMany<Model, $this>
     */
    private function placedOn(string $owner): BelongsToMany
    {
        return $this->belongsToMany($owner)->withPivot(['slot', 'group_label', 'sort_order'])->withTrashed(); // @phpstan-ignore method.notFound
    }

    public function isSuperseded(): bool
    {
        return $this->replacedBy()->exists();
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    public function keepsPublicArchive(): bool
    {
        return true;
    }

    /**
     * Documents are exposed through contextual listings and download URLs,
     * not automatic detail pages. A route is only assigned deliberately
     * (e.g. to preserve a migrated legacy file URL).
     */
    public static function createsRouteAutomatically(): bool
    {
        return false;
    }

    public static function defaultPathPrefix(): string
    {
        return '/dokumente';
    }

    /**
     * Public download path: a deliberately assigned (e.g. migrated legacy)
     * route takes precedence; otherwise the stable download URL
     * /download/{id}/{filename}.
     */
    public function downloadPath(): string
    {
        return $this->publicPath() ?? '/download/'.$this->getKey().'/'.$this->downloadFilename();
    }

    public function downloadFilename(): string
    {
        $name = Str::slug(pathinfo($this->original_filename, PATHINFO_FILENAME), '-', 'de');

        return ($name !== '' ? Str::limit($name, 120, '') : 'dokument').'.'.$this->extension;
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['title', 'description', 'category_id', 'year', 'document_date', 'valid_from', 'valid_until', 'language', 'accessibility_status', 'accessibility_notes', 'accessible_alternative_id', 'replaces_document_id'];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->title, (string) $this->description, array_values(array_filter([$this->category?->name, $this->year ? (string) $this->year : null])));
    }
}
