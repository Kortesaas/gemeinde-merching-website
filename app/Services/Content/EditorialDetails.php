<?php

namespace App\Services\Content;

use App\Enums\EventOperationalStatus;
use App\Enums\OnlineServiceMode;
use App\Exceptions\DomainRuleViolation;
use App\Models;
use App\Rules\ControlledText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Persistence rules also cover proposal application and revision restoration. */
final class EditorialDetails
{
    public function validate(Model $model): void
    {
        $text = ['nullable', 'string', 'max:10000', new ControlledText];
        $rules = match (true) {
            $model instanceof Models\Media => ['focal_x' => ['nullable', 'numeric', 'between:0,100'], 'focal_y' => ['nullable', 'numeric', 'between:0,100']],
            $model instanceof Models\Service => ['prerequisites' => $text, 'required_items' => $text, 'processing_duration' => $text, 'important_notice' => $text, 'online_service_mode' => ['required', Rule::enum(OnlineServiceMode::class)]],
            $model instanceof Models\Event => ['operational_status' => ['required', Rule::enum(EventOperationalStatus::class)], 'schedule_notice' => $text],
            $model instanceof Models\Location => ['accessibility_note' => $text],
            $model instanceof Models\CouncilTerm => ['starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']],
            $model instanceof Models\Committee => ['council_term_id' => ['required', 'integer', Rule::exists('council_terms', 'id')->whereNull('deleted_at')], 'sort_order' => ['nullable', 'integer', 'between:0,65535']],
            default => [],
        };
        if ($model instanceof Models\Service && $model->getAttribute('online_service_mode') === null) {
            $model->setAttribute('online_service_mode', OnlineServiceMode::NotSpecified);
        }
        if ($model instanceof Models\Event && $model->getAttribute('operational_status') === null) {
            $model->setAttribute('operational_status', EventOperationalStatus::Scheduled);
        }
        $data = $model->getAttributes();
        if (empty($data['starts_on'])) {
            $rules['ends_on'] = ['nullable', 'date'];
        }
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new DomainRuleViolation($validator->errors()->first(), (string) $validator->errors()->keys()[0]);
        }
    }
}
