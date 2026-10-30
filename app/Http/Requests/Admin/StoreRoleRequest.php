<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
                'regex:/^[a-z][a-z0-9-]*$/',
                'unique:roles,slug',
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
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
            'slug.regex' => __('The slug must be in kebab-case (lowercase letters, numbers, and hyphens, starting with a letter).'),
        ];
    }

    /**
     * Get the validated role data.
     *
     * @return array{name: string, slug: string, description: ?string}
     */
    public function roleData(): array
    {
        /** @var array{name: string, slug: string, description: ?string} $data */
        $data = $this->safe()->only(['name', 'slug', 'description']);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ];
    }

    /**
     * Get the validated permission IDs for role assignment.
     *
     * @return array<int, int>
     */
    public function permissionIds(): array
    {
        /** @var array<int, int|string> $permissions */
        $permissions = $this->validated('permissions', []);

        return array_values(array_map(
            static fn (int|string $id): int => (int) $id,
            $permissions,
        ));
    }
}
