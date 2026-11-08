<?php

namespace App\Http\Requests\Employer;

use App\Enums\ScreeningQuestionType;
use App\Models\Job;
use App\Models\ScreeningQuestion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobScreeningQuestionRequest extends FormRequest
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
            'question_id' => ['nullable', 'integer', 'exists:screening_questions,id'],
            'type' => ['nullable', 'string', Rule::in(array_column(ScreeningQuestionType::cases(), 'value'))],
            'label' => ['nullable', 'string', 'min:3', 'max:500'],
            'options' => ['nullable', 'array', 'min:2', 'max:10'],
            'options.*' => ['string', 'max:100', 'distinct'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Job|null $job */
            $job = $this->route('job');

            if (! $job) {
                $validator->errors()->add('job', __('The selected job could not be found.'));

                return;
            }

            /** @var User|null $user */
            $user = $this->user();

            $hasQuestionId = $this->filled('question_id');
            $hasInline = $this->filled('type') || $this->filled('label');

            if ($hasQuestionId === $hasInline) {
                $validator->errors()->add('question_id', __('Provide either an existing bank question or a new question payload.'));

                return;
            }

            if ($hasQuestionId) {
                if (! $user) {
                    return;
                }

                $owned = ScreeningQuestion::query()
                    ->where('id', $this->input('question_id'))
                    ->where('owner_user_id', $user->id)
                    ->exists();

                if (! $owned) {
                    $validator->errors()->add('question_id', __('The selected bank question is invalid.'));
                }
            } else {
                $type = $this->input('type');
                $label = $this->input('label');

                if (! in_array($type, array_column(ScreeningQuestionType::cases(), 'value'), true)) {
                    $validator->errors()->add('type', __('The selected type is invalid.'));
                }
                if (! is_string($label) || trim($label) === '') {
                    $validator->errors()->add('label', __('The label field is required.'));
                }

                if ($type === ScreeningQuestionType::SingleChoice->value) {
                    $options = $this->input('options');
                    if (! is_array($options) || count($options) < 2) {
                        $validator->errors()->add('options', __('Single choice questions require 2 to 10 options.'));
                    }
                } else {
                    $options = $this->input('options');
                    if (is_array($options) && $options !== []) {
                        $validator->errors()->add('options', __('Options are only allowed for single choice questions.'));
                    }
                }
            }

            if ($job->screeningQuestions()->count() >= 50) {
                $validator->errors()->add('job', __('This job already has the maximum number of screening questions.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function jobScreeningQuestionData(): array
    {
        if ($this->filled('question_id')) {
            return ['question_id' => (int) $this->input('question_id')];
        }

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
            'sort_order' => $this->input('sort_order'),
        ];
    }
}
