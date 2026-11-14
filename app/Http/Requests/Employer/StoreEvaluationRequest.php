<?php

namespace App\Http\Requests\Employer;

use App\Enums\EvaluationCriterion;
use App\Models\ApplicationEvaluation;
use App\Models\JobApplication;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        if (! $application instanceof JobApplication) {
            return false;
        }

        return $this->user()?->can('createForApplication', [ApplicationEvaluation::class, $application]) ?? false;
    }

    /**
     * @return array<string, array<int, string|Closure>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (EvaluationCriterion::cases() as $criterion) {
            $rules[$criterion->value] = ['required', $this->strictOneToFive($criterion->label())];
        }

        $rules['comment'] = ['nullable', 'string', 'max:2000'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = ['comment.max' => __('The comment cannot exceed 2,000 characters.')];

        foreach (EvaluationCriterion::cases() as $criterion) {
            $messages[$criterion->value.'.required'] = __(':label is required.', ['label' => $criterion->label()]);
        }

        return $messages;
    }

    private function strictOneToFive(string $label): Closure
    {
        // Strict: JSON ints only — rejects "5" strings, 3.5 floats (incl. 5.0), bools, and out-of-range values.
        return function (string $attribute, mixed $value, Closure $fail) use ($label): void {
            if (! is_int($value) || $value < 1 || $value > 5) {
                $fail(__(':label must be a whole number between 1 and 5.', ['label' => $label]));
            }
        };
    }
}
