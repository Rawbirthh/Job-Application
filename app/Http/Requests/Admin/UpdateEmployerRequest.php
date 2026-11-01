<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployerRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:pending,approved,suspended'],
            'suspension_reason' => ['nullable', 'string', 'min:1', 'max:500'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $status = $this->input('status');
            $reason = $this->input('suspension_reason');

            if ($status === 'suspended' && empty($reason)) {
                $validator->errors()->add('suspension_reason', 'Suspension reason is required when status is suspended.');
            }

            if (in_array($status, ['approved', 'rejected']) && ! empty($reason)) {
                $validator->errors()->add('suspension_reason', 'Suspension reason must not be set when status is approved or rejected.');
            }
        });
    }
}
