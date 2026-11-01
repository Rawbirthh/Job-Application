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

    /**
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $status = $this->input('status');
            $reason = $this->input('suspension_reason');

            if ($status === 'suspended' && empty($reason)) {
                $validator->errors()->add('suspension_reason', 'Suspension reason is required when status is suspended.');
            }

            if (in_array($status, ['approved', 'rejected'], true) && ! empty($reason)) {
                $validator->errors()->add('suspension_reason', 'Suspension reason must not be set when status is approved or rejected.');
            }
        });
    }
}
