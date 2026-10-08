<?php

namespace App\Admin\Fields;

use App\Rules\SiteDateTime;
use App\Support\SiteTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Date and time entered and shown in SITE_TIMEZONE, stored in UTC.
 */
class DateTime extends Field
{
    protected function baseRules(?Model $model): array
    {
        return ['string', new SiteDateTime];
    }

    protected function toAttribute(mixed $value): mixed
    {
        return SiteTime::fromInput(is_string($value) ? $value : null);
    }

    public function view(): string
    {
        return 'admin.fields.datetime';
    }

    public function formValue(Model $model): mixed
    {
        $value = $model->getAttribute($this->name);

        return $value instanceof DateTimeInterface ? SiteTime::toInput($value) : null;
    }

    public function display(Model $model): string
    {
        $value = $model->getAttribute($this->name);

        return $value instanceof DateTimeInterface ? SiteTime::format($value) : '';
    }
}
