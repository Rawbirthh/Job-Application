<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmploymentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:employment_types,name'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:employment_types,slug',
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array{name: string, slug: string, description: ?string, is_active: bool}
     */
    public function employmentTypeData(): array
    {
        /** @var array{name: string, slug: string, description: ?string, is_active?: bool} $data */
        $data = $this->safe()->all();

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
