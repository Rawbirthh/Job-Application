<?php

namespace App\Http\Requests\Candidate;

use App\Enums\AlertFrequency;
use App\Models\SavedSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var SavedSearch|null $savedSearch */
        $savedSearch = $this->route('savedSearch');

        return $user !== null
            && $savedSearch instanceof SavedSearch
            && $user->can('update', $savedSearch);
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
}
