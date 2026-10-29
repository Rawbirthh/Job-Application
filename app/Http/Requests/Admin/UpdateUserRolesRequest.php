<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRolesRequest extends FormRequest
{
    /**
     * The controller performs the actual UserPolicy authorization.
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
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
        ];
    }

    /**
     * Get the validated role IDs as a list.
     *
     * @return array<int, int>
     */
    public function roleIds(): array
    {
        /** @var array<int, int|string> $roles */
        $roles = $this->validated('roles', []);

        return array_values(array_map(
            static fn (int|string $roleId): int => (int) $roleId,
            $roles,
        ));
    }
}
