<?php

namespace App\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

class Text extends Field
{
    public int $max = 255;

    public string $inputType = 'text';

    public ?string $autocomplete = null;

    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function autocomplete(string $value): static
    {
        $this->autocomplete = $value;

        return $this;
    }

    protected function baseRules(?Model $model): array
    {
        return ['string', 'max:'.$this->max];
    }

    public function view(): string
    {
        return 'admin.fields.text';
    }
}
