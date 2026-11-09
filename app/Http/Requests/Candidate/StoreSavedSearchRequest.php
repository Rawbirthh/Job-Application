<?php

namespace App\Http\Requests\Candidate;

use App\Enums\AlertFrequency;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', SavedSearch::class);
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'search' => is_string($this->search) ? Str::trim($this->search) : '',
            'name' => is_string($this->name) ? Str::trim($this->name) : '',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'name' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:job_categories,id'],
            'employment_type_id' => ['nullable', 'integer', 'exists:employment_types,id'],
            'workplace_arrangement_id' => ['nullable', 'integer', 'exists:workplace_arrangements,id'],
            'experience_level_id' => ['nullable', 'integer', 'exists:experience_levels,id'],
            'alerts_enabled' => ['boolean'],
            'alert_frequency' => ['string', Rule::in(array_map(
                static fn (AlertFrequency $frequency): string => $frequency->value,
                AlertFrequency::cases()
            ))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if ($user instanceof User && $user->savedSearches()->count() >= 20) {
                $validator->errors()->add('searches', __('You can save up to 20 saved searches. Delete one to save another.'));
            }
        });
    }
}
