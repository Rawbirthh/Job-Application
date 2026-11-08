<?php

namespace App\Http\Requests\Employer;

use App\Enums\ScreeningQuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScreeningQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('label'))) {
            $this->merge(['label' => trim($this->input('label'))]);
        }

        $options = $this->input('options');
        if (is_array($options)) {
            $normalized = [];
            foreach ($options as $option) {
                if (is_string($option)) {
                    $normalized[] = trim($option);
                } else {
                    $normalized[] = $option;
                }
            }
            $this->merge(['options' => count($normalized) === 0 ? null : $normalized]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_column(ScreeningQuestionType::cases(), 'value'))],
            'label' => ['required', 'string', 'min:3', 'max:500'],
            'options' => ['required_if:type,single_choice', 'nullable', 'array', 'min:2', 'max:10'],
            'options.*' => ['string', 'max:100', 'distinct'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $options = $this->input('options');

            if ($type === ScreeningQuestionType::SingleChoice->value) {
                if (! is_array($options) || count($options) < 2) {
                    $validator->errors()->add('options', __('Single choice questions require 2 to 10 options.'));
                }
            } else {
                if (is_array($options) && $options !== []) {
                    $validator->errors()->add('options', __('Options are only allowed for single choice questions.'));
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function screeningQuestionData(): array
    {
        $validated = $this->validated();

        $type = $validated['type'];
        $options = null;
        if ($type === ScreeningQuestionType::SingleChoice->value) {
            $options = array_values($validated['options'] ?? []);
        }

        return [
            'type' => $type,
            'label' => $validated['label'],
            'options' => $options,
            'is_required' => $this->boolean('is_required'),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
