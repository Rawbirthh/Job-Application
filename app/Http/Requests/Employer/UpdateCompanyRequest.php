<?php

namespace App\Http\Requests\Employer;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = ['name', 'overview', 'industry', 'website', 'size_band', 'location'];

        $merged = [];
        foreach ($fields as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                if (in_array($field, ['overview', 'industry', 'website', 'size_band', 'location'], true)) {
                    $value = $value === '' ? null : $value;
                }
            }
            $merged[$field] = $value;
        }

        $slug = $this->input('slug');
        $merged['slug'] = is_string($slug) ? (trim($slug) === '' ? null : trim($slug)) : $slug;

        $this->merge($merged);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = $this->route('company');
        $companyId = $company instanceof Company ? $company->getKey() : $company;

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('companies', 'slug')->ignore($companyId)],
            'overview' => ['nullable', 'string', 'max:5000'],
            'industry' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'size_band' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function companyData(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'overview' => $validated['overview'] ?? null,
            'industry' => $validated['industry'] ?? null,
            'website' => $validated['website'] ?? null,
            'size_band' => $validated['size_band'] ?? null,
            'location' => $validated['location'] ?? null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ];
    }
}
