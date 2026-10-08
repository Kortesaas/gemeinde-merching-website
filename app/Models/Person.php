<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Contracts\Searchable;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\HasSourceReferences;
use App\Support\Search\SearchDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee / contact person. No portrait field (explicit requirement).
 * Former employees are deactivated, not deleted, because records reference them.
 *
 * @property int $id
 * @property string|null $salutation
 * @property string|null $academic_title
 * @property string|null $first_name
 * @property string $last_name
 * @property string|null $display_name
 * @property string|null $job_title
 * @property string|null $responsibilities
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 */
#[Fillable(['salutation', 'academic_title', 'first_name', 'last_name', 'display_name', 'job_title', 'responsibilities', 'phone', 'fax', 'email', 'room', 'availability', 'public_notes', 'is_active', 'sort_order'])]
class Person extends Model implements Revisionable, Searchable
{
    use HasRevisions, HasSourceReferences, SoftDeletes;

    protected $table = 'people';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withPivot(['function_label', 'sort_order'])->withTimestamps();
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withPivot('sort_order')->withTimestamps();
    }

    public function displayTitle(): string
    {
        if ($this->display_name) {
            return $this->display_name;
        }

        return trim(implode(' ', array_filter([$this->academic_title, $this->first_name, $this->last_name])));
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['salutation', 'academic_title', 'first_name', 'last_name', 'display_name', 'job_title', 'responsibilities', 'phone', 'fax', 'email', 'room', 'availability', 'public_notes', 'is_active', 'sort_order'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function revisionRelations(): array
    {
        return ['departments' => ['function_label', 'sort_order']];
    }

    public function toSearchDocument(): SearchDocument
    {
        return new SearchDocument($this->displayTitle(), (string) $this->job_title, $this->responsibilities ? [$this->responsibilities] : []);
    }
}
