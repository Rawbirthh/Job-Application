<?php

namespace App\Http\Requests\Employer;

use App\Enums\InterviewStatus;
use App\Http\Requests\Concerns\ConvertsLocalDateTimeToUtc;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelInterviewRequest extends FormRequest
{
    use ConvertsLocalDateTimeToUtc;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $interview = $this->route('interview');
        $user = $this->user();

        return $interview instanceof Interview
            && $user instanceof User
            && $user->can('manage', $interview);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeStringFields(['cancellation_reason']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_status' => ['required', 'string', Rule::enum(InterviewStatus::class)],
            'current_scheduled_at' => ['required', 'date'],
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * Get the validated data mapped for the interview service.
     *
     * @return array<string, mixed>
     */
    public function cancelData(): array
    {
        $validated = $this->validated();

        return [
            'current_status' => (string) $validated['current_status'],
            'current_scheduled_at' => (string) $validated['current_scheduled_at'],
            'cancellation_reason' => (string) $validated['cancellation_reason'],
        ];
    }
}
