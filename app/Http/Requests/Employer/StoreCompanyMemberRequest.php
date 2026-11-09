<?php

namespace App\Http\Requests\Employer;

use App\Enums\CompanyMemberRole;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyMemberRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'company_role' => ['required', 'string', Rule::in(array_map(
                static fn (CompanyMemberRole $role): string => $role->value,
                CompanyMemberRole::cases()
            ))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Company|null $company */
            $company = $this->route('company');
            $email = $this->input('email');

            if (! is_string($email)) {
                return;
            }

            $target = User::where('email', $email)->first();

            if (! $target instanceof User || ! $target->isApprovedEmployer()) {
                $validator->errors()->add('email', __('The selected user must be an approved employer.'));

                return;
            }

            if ((int) $company->owner_user_id === (int) $target->id) {
                $validator->errors()->add('email', __('The company owner cannot be added as a member.'));

                return;
            }

            $actor = $this->user();

            if ($actor instanceof User && (int) $actor->id === (int) $target->id) {
                $validator->errors()->add('email', __('You cannot add yourself as a member.'));

                return;
            }

            $duplicate = CompanyMember::query()
                ->where('company_id', $company->id)
                ->where('user_id', $target->id)
                ->whereIn('status', ['active', 'pending'])
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('email', __('This user is already a member of the company.'));
            }
        });
    }
}
