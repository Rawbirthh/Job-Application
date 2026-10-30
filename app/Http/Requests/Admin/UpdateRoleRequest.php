<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
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
        $roleId = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    /**
     * Get the validated role data (slug is not updateable).
     *
     * @return array{name: string, description: ?string}
     */
    public function roleData(): array
    {
        /** @var array{name: string, description: ?string} $data */
        $data = $this->safe()->only(['name', 'description']);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ];
    }

    /**
     * Get the validated permission IDs for role sync.
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
