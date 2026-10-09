<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Contracts\Routable;
use App\Contracts\Searchable;
use App\Models\Concerns\HasDocumentPlacements;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasProposals;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasPublicRoute;
use App\Models\Concerns\HasResourcePlacements;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSourceReferences;
use App\Models\Concerns\TracksEditors;
use App\Support\Search\SearchDocument;
use App\Support\SiteTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Calendar event. Venue: internal Location or free text; organiser: internal
 * Organization or free text. Recurrence is stored as an RFC 5545 RRULE
 * (expansion follows in a later phase).
 *
 * auto_archive: expires_at is derived from the end of the event on every save,
 * so the event leaves current listings automatically – no cron job.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $all_day
 * @property string|null $recurrence_rule
 * @property bool $auto_archive
 */
#[Fillable(['title', 'description', 'starts_at', 'ends_at', 'all_day', 'recurrence_rule', 'location_id', 'venue', 'organization_id', 'organizer_name', 'contact_person_id', 'remarks', 'category_id', 'url', 'registration_url', 'auto_archive'])]
class Event extends Model implements Proposable, Routable, Searchable
{
    use HasDocumentPlacements, HasMedia, HasProposals, HasPublication, HasPublicRoute, HasResourcePlacements, HasRevisions, HasSourceReferences, SoftDeletes, TracksEditors;

    protected static function booted(): void
    {
        static::saving(function (Event $event) {
            if ($event->auto_archive) {
                $event->expires_at = $event->endsAtForArchiving();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'auto_archive' => 'boolean',
        ];
    }

    /**
     * Events that have not ended yet, soonest first.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->where('ends_at', '>=', now())
            ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->subDay())))
            ->orderBy('starts_at');
    }

    public function endsAtForArchiving(): CarbonImmutable
    {
        $end = $this->ends_at ?? $this->starts_at;

        // All-day events last until the end of that day in the site time zone.
        return $this->all_day ? SiteTime::fromUtc($end)->endOfDay()->utc() : $end;
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function contactPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'contact_person_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public static function documentSlots(): array
    {
        return ['anhaenge' => 'Anhänge'];
    }

    public static function resourceSlots(): array
    {
        return ['links' => 'Links'];
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    public function keepsPublicArchive(): bool
    {
        return true;
    }

    public static function defaultPathPrefix(): string
    {
        return '/veranstaltungen';
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['seo_title', 'meta_description', 'seo_noindex', 'title', 'description', 'starts_at', 'ends_at', 'all_day', 'recurrence_rule', 'location_id', 'venue', 'organization_id', 'organizer_name', 'contact_person_id', 'remarks', 'category_id', 'url', 'registration_url', 'auto_archive'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array
    {
        return [
            'media' => ['sort_order'],
            'documents' => ['slot', 'group_label', 'sort_order'],
            'externalResources' => ['slot', 'group_label', 'sort_order'],
        ];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->title, '', array_values(array_filter([$this->category?->name, $this->venue, $this->organizer_name])), (string) $this->description);
    }
}
