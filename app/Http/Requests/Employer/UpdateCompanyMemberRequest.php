<?php

namespace App\Http\Requests\Employer;

use App\Enums\CompanyMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_role' => ['required', 'string', Rule::in(array_map(
                static fn (CompanyMemberRole $role): string => $role->value,
                CompanyMemberRole::cases()
            ))],
        ];
    }
}
