<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class DismissReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('dismiss', $this->route('report'));
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'internal_note' => is_string($this->internal_note) ? Str::trim($this->internal_note) : $this->internal_note,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
