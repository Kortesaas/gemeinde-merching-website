<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Enums\AlertSeverity;
use App\Models\Concerns\HasProposals;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\TracksEditors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Temporary high-priority banner (road closure, changed opening hours).
 * Uses the normal publication lifecycle; visible() decides what is shown.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property AlertSeverity $severity
 * @property string|null $link_url
 * @property string|null $link_label
 */
#[Fillable(['title', 'body', 'severity', 'link_url', 'link_label'])]
class SiteAlert extends Model implements Proposable
{
    use HasProposals, HasPublication, HasRevisions, SoftDeletes, TracksEditors;

    protected function casts(): array
    {
        return ['severity' => AlertSeverity::class];
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    /**
     * @return list<string>
     */
    public function revisionAttributes(): array
    {
        return ['title', 'body', 'severity', 'link_url', 'link_label'];
    }
}
