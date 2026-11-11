<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReportResolutionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resolve', $this->route('report'));
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'resolution_action' => is_string($this->resolution_action) ? Str::trim($this->resolution_action) : $this->resolution_action,
            'resolution_message' => is_string($this->resolution_message) ? Str::trim($this->resolution_message) : $this->resolution_message,
            'internal_note' => is_string($this->internal_note) ? Str::trim($this->internal_note) : $this->internal_note,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'resolution_action' => ['required', Rule::in(ReportResolutionAction::values())],
            'resolution_message' => ['nullable', 'string', 'max:1000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
