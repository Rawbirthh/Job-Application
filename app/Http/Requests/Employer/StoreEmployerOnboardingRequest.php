<?php

namespace App\Http\Requests\Employer;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployerOnboardingRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'min:2', 'max:150'],
            'company_website' => ['nullable', 'string', 'url', 'max:255'],
            'job_title' => ['required', 'string', 'min:2', 'max:100'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Normalize input before validation.
     */
    protected function prepareForValidation(): void
    {
        $website = $this->input('company_website');
        $message = $this->input('message');

        $this->merge([
            'company_name' => is_string($this->input('company_name'))
                ? trim($this->input('company_name'))
                : $this->input('company_name'),
            'company_website' => is_string($website) ? trim($website) ?: null : $website,
            'job_title' => is_string($this->input('job_title'))
                ? trim($this->input('job_title'))
                : $this->input('job_title'),
            'message' => is_string($message) ? trim($message) ?: null : $message,
        ]);
    }
}
