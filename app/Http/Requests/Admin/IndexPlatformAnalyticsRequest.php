<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ResolvesAnalyticsWindow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Read-only filter input for the platform analytics index.
 */
class IndexPlatformAnalyticsRequest extends FormRequest
{
    use ResolvesAnalyticsWindow;

    /**
     * Authorization is enforced by the route middleware
     * (permission:admin.access and permission:platform_analytics.view).
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
        return $this->analyticsWindowRules();
    }

    /**
     * Run the post-validation checks.
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateAnalyticsWindow($validator);
    }
}
