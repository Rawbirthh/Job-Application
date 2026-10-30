<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get the validated permission data (slug is not updateable).
     *
     * @return array{name: string, description: ?string}
     */
    public function permissionData(): array
    {
        /** @var array{name: string, description: ?string} $data */
        $data = $this->safe()->only(['name', 'description']);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ];
    }
}
