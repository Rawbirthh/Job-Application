<?php

namespace App\Http\Requests\Employer;

use App\Models\ApplicationNote;
use App\Models\JobApplication;
use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        if (! $application instanceof JobApplication) {
            return false;
        }

        return $this->user()?->can('createForApplication', [ApplicationNote::class, $application]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        $this->merge(['body' => is_string($body) ? trim($body) : '']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('A note cannot be empty.'),
            'body.max' => __('A note cannot exceed 5,000 characters.'),
        ];
    }
}
