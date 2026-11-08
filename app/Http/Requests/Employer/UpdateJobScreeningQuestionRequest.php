<?php

namespace App\Http\Requests\Employer;

use App\Enums\ScreeningQuestionType;
use App\Models\JobScreeningQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateJobScreeningQuestionRequest extends FormRequest
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
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
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

            /** @var JobScreeningQuestion|null $question */
            $question = $this->route('question');

            if (! $question) {
                return;
            }

            if ($question->answers()->exists()) {
                if ($type !== $question->type->value) {
                    $validator->errors()->add('type', __('This question has submitted answers and its type cannot be changed.'));
                }
                if (trim((string) $this->input('label')) !== trim((string) $question->label)) {
                    $validator->errors()->add('label', __('This question has submitted answers and its label cannot be changed.'));
                }

                $submittedOptions = $type === ScreeningQuestionType::SingleChoice->value
                    ? array_values($options ?? [])
                    : null;
                $currentOptions = $question->options;
                if ($submittedOptions !== $currentOptions) {
                    // normalize comparison
                    if (json_encode($submittedOptions) !== json_encode($currentOptions)) {
                        $validator->errors()->add('options', __('This question has submitted answers and its options cannot be changed.'));
                    }
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function jobScreeningQuestionData(): array
    {
        $validated = $this->validated();
        $type = $validated['type'];
        $options = null;
        if ($type === ScreeningQuestionType::SingleChoice->value) {
            $options = array_values($validated['options'] ?? []);
        }

        $data = [
            'type' => $type,
            'label' => $validated['label'],
            'options' => $options,
            'is_required' => $this->boolean('is_required'),
            'is_active' => $this->boolean('is_active'),
        ];

        if ($this->has('sort_order')) {
            $data['sort_order'] = $this->input('sort_order');
        }

        return $data;
    }
}
