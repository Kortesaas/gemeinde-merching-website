<?php

namespace App\Models;

use App\Contracts\Revisionable;
use App\Models\Concerns\HasRevisions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phrase', 'alternatives', 'is_active'])]
class SearchSynonym extends Model implements Revisionable
{
    use HasRevisions;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function displayTitle(): string
    {
        return $this->phrase;
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['phrase', 'alternatives', 'is_active'];
    }
}
