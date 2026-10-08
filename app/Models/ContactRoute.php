<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Topic of the future contact form ("Meldewesen", "Bauamt", …). Visitors
 * choose the topic, never an address.
 *
 * Recipient addresses are internal: encrypted at rest (APP_KEY), hidden from
 * serialisation, excluded from revisions/audit metadata and only shown in the
 * backend to users with contact-route.edit. Public code must use publicData().
 *
 * @property int $id
 * @property string $label
 * @property string|null $explanation
 * @property list<string> $recipients
 * @property bool $is_active
 */
#[Fillable(['label', 'explanation', 'recipients', 'department_id', 'is_active', 'sort_order'])]
#[Hidden(['recipients'])]
class ContactRoute extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['recipients' => 'encrypted:array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The only data public code may use.
     *
     * @return array{id: int, label: string, explanation: string|null}
     */
    public function publicData(): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'explanation' => $this->explanation];
    }

    public function displayTitle(): string
    {
        return $this->label;
    }
}
