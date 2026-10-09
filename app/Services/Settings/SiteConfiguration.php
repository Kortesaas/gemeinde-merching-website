<?php

namespace App\Services\Settings;

use App\Models\SiteSettings;

final class SiteConfiguration
{
    public function current(): ?SiteSettings
    {
        return SiteSettings::query()->find(1);
    }

    public function name(): string
    {
        return $this->current()?->getAttribute('municipality_name') ?? (string) config('app.name');
    }
}
