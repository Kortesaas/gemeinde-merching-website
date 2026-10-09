<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Models\Concerns\HasProposals;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\TracksEditors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $title */
#[Fillable(['title', 'description'])]
class CouncilMember extends Model implements Proposable
{
    use HasProposals, HasPublication, HasRevisions, SoftDeletes, TracksEditors;

    public function keepsPublicArchive(): bool
    {
        return true;
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['title', 'description'];
    }
}
