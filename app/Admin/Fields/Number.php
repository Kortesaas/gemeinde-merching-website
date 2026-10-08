<?php

namespace App\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

class Number extends Field
{
    public int|float $min = 0;

    public int|float $max = 65535;

    public string $step = '1';

    public function between(int|float $min, int|float $max, string $step = '1'): static
    {
        [$this->min, $this->max, $this->step] = [$min, $max, $step];

        return $this;
    }

    protected function baseRules(?Model $model): array
    {
        return [$this->step === '1' ? 'integer' : 'numeric', 'min:'.$this->min, 'max:'.$this->max];
    }

    public function view(): string
    {
        return 'admin.fields.number';
    }
}
