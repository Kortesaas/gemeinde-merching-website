<?php

namespace App\Policies;

use App\Models\SiteSettings;
use App\Models\User;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

class SiteSettingsPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::SiteSettings;
    }

    public function create(User $user): bool
    {
        return parent::create($user) && ! SiteSettings::query()->exists();
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
