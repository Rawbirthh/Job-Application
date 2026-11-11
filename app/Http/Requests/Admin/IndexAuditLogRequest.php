<?php

namespace App\Http\Requests\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Read-only filter input for the administrative audit log index.
 */
class IndexAuditLogRequest extends FormRequest
{
    /**
     * The controller performs the actual AuditLogPolicy authorization.
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
        $this->merge([
            'action' => $this->normalizedString('action'),
            'target_type' => $this->normalizedString('target_type'),
            'search' => $this->normalizedString('search'),
            'from' => $this->normalizedString('from'),
            'to' => $this->normalizedString('to'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', Rule::in(AuditAction::values())],
            'target_type' => ['nullable', 'string', Rule::in(AuditTargetType::values())],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Run the post-validation checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (is_string($from) && is_string($to) && $from > $to) {
                $validator->errors()->add('to', __('The end date must be on or after the start date.'));
            }
        });
    }

    /**
     * Read a filter value as a trimmed string, mapping empty values to null.
     *
     * A non-string value is left untouched so the validation rules reject it.
     */
    private function normalizedString(string $key): mixed
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
