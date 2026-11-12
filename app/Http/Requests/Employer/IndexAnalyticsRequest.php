<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\Concerns\ResolvesAnalyticsWindow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Read-only filter input for the employer analytics index.
 */
class IndexAnalyticsRequest extends FormRequest
{
    use ResolvesAnalyticsWindow;

    /**
     * The controller performs the actual CompanyPolicy::viewAnalytics authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the filter input before validation.
     */
    public function prepareForValidation(): void
    {
        $this->normalizeAnalyticsWindow();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            ...$this->analyticsWindowRules(),
        ];
    }

    /**
     * Run the post-validation checks.
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateAnalyticsWindow($validator);
    }
}
