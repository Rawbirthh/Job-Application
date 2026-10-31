<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExperienceLevelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', 'unique:experience_levels,name'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:experience_levels,slug',
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array{name: string, slug: string, description: ?string, sort_order: int, is_active: bool}
     */
    public function experienceLevelData(): array
    {
        /** @var array{name: string, slug: string, description?: ?string, sort_order?: int, is_active?: bool} $data */
        $data = $this->safe()->all();

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
