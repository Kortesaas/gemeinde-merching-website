<?php

namespace App\Admin\Fields;

use App\Exceptions\DomainRuleViolation;
use App\Models\BudgetSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class BudgetComponents extends Rows
{
    public ?int $planId = null;

    public function forPlan(?int $id): static
    {
        $this->planId = $id;

        return $this;
    }

    public function columns(): array
    {
        return [
            'sort_order' => ['label' => 'Position', 'rules' => ['required', 'integer', 'min:0', 'max:65535'], 'type' => 'number'],
            'budget_source_id' => ['label' => 'PDF-Datei', 'rules' => ['required', 'integer', Rule::exists('budget_sources', 'id')->where('budget_plan_id', $this->planId)], 'options' => BudgetSource::query()->where('budget_plan_id', $this->planId)->get()->mapWithKeys(fn ($s) => [$s->getKey() => $s->original_filename.' · Upload #'.$s->getKey()])->all()],
        ];
    }

    public function view(): string
    {
        return 'admin.fields.budget-components';
    }

    public function afterSave(Model $model, array $data): void
    {
        foreach ((array) ($data[$this->name] ?? []) as $row) {
            if (! empty($row['_remove']) || empty($row['budget_source_id'])) {
                continue;
            }
            if (! BudgetSource::query()->where('budget_plan_id', $model->getKey())->whereKey($row['budget_source_id'])->exists()) {
                throw new DomainRuleViolation('Bitte eine PDF-Datei dieses Haushaltsplans auswählen.', 'components');
            }
        }
        parent::afterSave($model, $data);
    }
}
