<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Models\Concerns\HasRevisions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['municipality_name', 'town_hall_location_id', 'central_department_id', 'central_contact_route_id', 'works_department_id', 'recycling_location_id', 'homepage_media_id', 'greeting_text', 'greeting_name', 'greeting_role', 'greeting_page_id', 'greeting_media_id', 'postal_address', 'legal_contact', 'default_seo_title', 'default_meta_description'])]
class SiteSettings extends Model implements Revisionable
{
    use HasRevisions;

    public $incrementing = false;

    /** @return BelongsTo<Location,$this> */
    public function townHall(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'town_hall_location_id');
    }

    /** @return BelongsTo<Department,$this> */
    public function centralDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'central_department_id');
    }

    /** @return BelongsTo<ContactRoute,$this> */
    public function centralContactRoute(): BelongsTo
    {
        return $this->belongsTo(ContactRoute::class, 'central_contact_route_id');
    }

    /** @return BelongsTo<Department,$this> */
    public function worksDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'works_department_id');
    }

    /** @return BelongsTo<Location,$this> */
    public function recyclingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'recycling_location_id');
    }

    /** @return BelongsTo<Media,$this> */
    public function homepageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'homepage_media_id');
    }

    /** @return BelongsTo<Media,$this> */
    public function greetingMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'greeting_media_id');
    }

    /** @return BelongsTo<Page,$this> */
    public function greetingPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'greeting_page_id');
    }

    /** Settings are rendered on every public page; their homepage image is public context. */
    public function isPubliclyReachable(): bool
    {
        return true;
    }

    public function displayTitle(): string
    {
        return $this->municipality_name;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->setAttribute('id', 1);
        });
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['municipality_name', 'town_hall_location_id', 'central_department_id', 'central_contact_route_id', 'works_department_id', 'recycling_location_id', 'homepage_media_id', 'greeting_text', 'greeting_name', 'greeting_role', 'greeting_page_id', 'greeting_media_id', 'postal_address', 'legal_contact', 'default_seo_title', 'default_meta_description'];
    }
}
