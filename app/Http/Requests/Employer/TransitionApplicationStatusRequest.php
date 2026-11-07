<?php

namespace App\Http\Requests\Employer;

use App\Enums\JobApplicationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionApplicationStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application && $this->user()->can('transitionStatus', $application);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_status' => ['required', 'string', Rule::enum(JobApplicationStatus::class)],
            'new_status' => ['required', 'string', Rule::enum(JobApplicationStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
