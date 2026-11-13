<?php

namespace App\Http\Requests\Employer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = ['title', 'description', 'responsibilities', 'requirements', 'location', 'salary_currency'];

        $merged = [];
        foreach ($fields as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                if (in_array($field, ['responsibilities', 'requirements', 'location', 'salary_currency'], true)) {
                    $value = $value === '' ? null : $value;
                }
            }
            $merged[$field] = $value;
        }

        $merged['skills'] = $this->normalizeSkills($this->input('skills'));

        $this->merge($merged);
    }

    /**
     * Trim the submitted skill list and drop empty entries.
     *
     * @return array<int, string>
     */
    protected function normalizeSkills(mixed $skills): array
    {
        if (! is_array($skills)) {
            return [];
        }

        $normalized = [];

        foreach ($skills as $skill) {
            if (! is_string($skill)) {
                continue;
            }

            $skill = trim($skill);

            if ($skill !== '') {
                $normalized[] = $skill;
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['required', 'string', 'max:20000'],
            'responsibilities' => ['nullable', 'string', 'max:20000'],
            'requirements' => ['nullable', 'string', 'max:20000'],
            'category_id' => ['required', 'exists:job_categories,id,is_active,1'],
            'employment_type_id' => ['required', 'exists:employment_types,id,is_active,1'],
            'workplace_arrangement_id' => ['nullable', 'exists:workplace_arrangements,id,is_active,1'],
            'experience_level_id' => ['nullable', 'exists:experience_levels,id,is_active,1'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'salary_negotiable' => ['boolean'],
            'pay_period_id' => ['nullable', 'exists:pay_periods,id,is_active,1'],
            'location' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*' => ['string', 'max:50'],
            'application_deadline' => ['nullable', 'date'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateSalaryRules($validator);
        });
    }

    /**
     * Validate salary cross-field rules.
     */
    protected function validateSalaryRules(Validator $validator): void
    {
        $salaryMin = $this->input('salary_min');
        $salaryMax = $this->input('salary_max');
        $hasSalary = $salaryMin !== null || $salaryMax !== null;

        if ($hasSalary) {
            if (empty($this->input('salary_currency'))) {
                $validator->errors()->add('salary_currency', 'The salary currency field is required when salary is provided.');
            }

            if (empty($this->input('pay_period_id'))) {
                $validator->errors()->add('pay_period_id', 'The pay period field is required when salary is provided.');
            }

            if ($salaryMin !== null && $salaryMax !== null && $salaryMin > $salaryMax) {
                $validator->errors()->add('salary_min', 'The salary min must be less than or equal to salary max.');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jobData(): array
    {
        $validated = $this->validated();

        return [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'responsibilities' => $validated['responsibilities'] ?? null,
            'requirements' => $validated['requirements'] ?? null,
            'category_id' => $validated['category_id'],
            'employment_type_id' => $validated['employment_type_id'],
            'workplace_arrangement_id' => $validated['workplace_arrangement_id'] ?? null,
            'experience_level_id' => $validated['experience_level_id'] ?? null,
            'salary_min' => $validated['salary_min'] ?? null,
            'salary_max' => $validated['salary_max'] ?? null,
            'salary_currency' => $validated['salary_currency'] ?? null,
            'salary_negotiable' => $this->boolean('salary_negotiable'),
            'pay_period_id' => $validated['pay_period_id'] ?? null,
            'location' => $validated['location'] ?? null,
            'skills' => array_values($validated['skills'] ?? []),
            'application_deadline' => $validated['application_deadline'] ?? null,
        ];
    }
}
