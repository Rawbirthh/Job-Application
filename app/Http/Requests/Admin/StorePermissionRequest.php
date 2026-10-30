<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    /**
     * The controller performs the actual authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9-.]*$/',
                'unique:permissions,slug',
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('The slug must be in kebab-case with optional dots (lowercase letters, numbers, hyphens, and dots, starting with a letter).'),
        ];
    }

    /**
     * Get the validated permission data.
     *
     * @return array{name: string, slug: string, description: ?string}
     */
    public function permissionData(): array
    {
        /** @var array{name: string, slug: string, description: ?string} $data */
        $data = $this->safe()->only(['name', 'slug', 'description']);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ];
    }
}
