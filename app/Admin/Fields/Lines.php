<?php

namespace App\Admin\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * One value per line (textarea), stored either in an array attribute
 * (e.g. encrypted recipient list) or via custom save callbacks (e.g. aliases).
 */
class Lines extends Field
{
    public int $rows = 4;

    public int $maxLines = 30;

    /** @var list<mixed> */
    public array $lineRules = ['string', 'max:255'];

    /** @var (Closure(Model): list<string>)|null */
    protected ?Closure $reader = null;

    /** @var (Closure(Model, list<string>): void)|null */
    protected ?Closure $writer = null;

    /**
     * @param  list<mixed>  $rules
     */
    public function eachLine(array $rules, int $maxLines = 30): static
    {
        [$this->lineRules, $this->maxLines] = [$rules, $maxLines];

        return $this;
    }

    /**
     * Store via relation instead of an attribute.
     *
     * @param  Closure(Model): list<string>  $reader
     * @param  Closure(Model, list<string>): void  $writer
     */
    public function using(Closure $reader, Closure $writer): static
    {
        [$this->reader, $this->writer] = [$reader, $writer];

        return $this;
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    public static function split($value): array
    {
        $lines = preg_split('/\R/u', is_string($value) ? $value : '') ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $lines), fn ($l) => $l !== '')));
    }

    public function validationRules(?Model $model): array
    {
        return [$this->name => [$this->required ? 'required' : 'nullable', 'string', 'max:'.($this->maxLines * 300)]];
    }

    protected function baseRules(?Model $model): array
    {
        return [];
    }

    public function fill(Model $model, array $data): void
    {
        if ($this->writer === null && array_key_exists($this->name, $data)) {
            $model->setAttribute($this->name, self::split($data[$this->name]));
        }
    }

    public function afterSave(Model $model, array $data): void
    {
        if ($this->writer !== null && array_key_exists($this->name, $data)) {
            ($this->writer)($model, self::split($data[$this->name]));
        }
    }

    public function formValue(Model $model): mixed
    {
        $lines = $this->reader ? ($this->reader)($model) : (array) ($model->getAttribute($this->name) ?? []);

        return implode("\n", $lines);
    }

    public function view(): string
    {
        return 'admin.fields.textarea';
    }

    public function display(Model $model): string
    {
        return str_replace("\n", ', ', (string) $this->formValue($model));
    }
}
