<?php

namespace App\Models;

use App\Exceptions\DomainRuleViolation;
use App\Services\Content\RowDefinitions;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One bounded component with explicit reference FKs; no JSON content payload.
 *
 * @property string $type
 * @property int $sort_order
 * @property string|null $heading
 * @property int|null $heading_level
 * @property string|null $text
 */
#[Fillable(['type', 'sort_order', 'heading', 'heading_level', 'text', 'media_id', 'gallery_id', 'document_id', 'person_id', 'department_id', 'service_id', 'event_id', 'location_id', 'external_resource_id'])]
class ContentBlock extends Model
{
    public const REFERENCES = ['media_id' => Media::class, 'gallery_id' => Gallery::class, 'document_id' => Document::class, 'person_id' => Person::class, 'department_id' => Department::class, 'service_id' => Service::class, 'event_id' => Event::class, 'location_id' => Location::class, 'external_resource_id' => ExternalResource::class];

    public const COLUMNS = ['sort_order', 'type', 'heading', 'heading_level', 'text', 'media_id', 'gallery_id', 'document_id', 'person_id', 'department_id', 'service_id', 'event_id', 'location_id', 'external_resource_id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'heading_level' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::saving(function (self $block) {
            app(RowDefinitions::class)->validate('blocks', $block->getAttributes());
            if (! in_array($block->getAttribute('owner_type'), ['page', 'article', 'event', 'notice', 'service', 'life-situation'], true)) {
                throw new DomainRuleViolation('Dieser Inhalt unterstützt keine Inhaltsblöcke.', 'blocks');
            }
            $class = MorphMap::MAP[$block->getAttribute('owner_type')];
            if (! $class::withTrashed()->whereKey((int) $block->getAttribute('owner_id'))->exists()) {
                throw new DomainRuleViolation('Der zugehörige Inhalt existiert nicht.', 'blocks');
            }
        });
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /** @return BelongsTo<Gallery, $this> */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'gallery_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /** @return BelongsTo<ExternalResource, $this> */
    public function externalResource(): BelongsTo
    {
        return $this->belongsTo(ExternalResource::class, 'external_resource_id');
    }

    public function referenced(): ?Model
    {
        foreach (self::REFERENCES as $column => $class) {
            if ($this->getAttribute($column) !== null) {
                return $class::query()->find((int) $this->getAttribute($column));
            }
        }

        return null;
    }
}
