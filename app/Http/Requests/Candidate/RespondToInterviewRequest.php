<?php

namespace App\Http\Requests\Candidate;

use App\Enums\InterviewStatus;
use App\Http\Requests\Concerns\ConvertsLocalDateTimeToUtc;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RespondToInterviewRequest extends FormRequest
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
            && $user->can('respond', $interview);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeStringFields(
            ['response', 'requested_at_local', 'requested_timezone', 'response_note'],
            ['requested_at_local', 'requested_timezone', 'response_note'],
        );

        if ($this->input('response') !== InterviewStatus::RescheduleRequested->value) {
            $this->merge([
                'requested_at_local' => null,
                'requested_timezone' => null,
                'requested_at_utc' => null,
            ]);

            return;
        }

        $this->convertLocalDateTimeToUtc('requested_at_local', 'requested_timezone', 'requested_at_utc');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requestingNewTime = $this->input('response') === InterviewStatus::RescheduleRequested->value;

        return [
            'current_status' => ['required', 'string', Rule::enum(InterviewStatus::class)],
            'current_scheduled_at' => ['required', 'date'],
            'response' => [
                'required',
                'string',
                Rule::in([
                    InterviewStatus::Accepted->value,
                    InterviewStatus::Declined->value,
                    InterviewStatus::RescheduleRequested->value,
                ]),
            ],
            'requested_at_local' => [
                Rule::requiredIf($requestingNewTime),
                'nullable',
                'string',
                'date_format:Y-m-d\TH:i',
            ],
            'requested_timezone' => [
                Rule::requiredIf($requestingNewTime),
                'nullable',
                'string',
                'timezone',
            ],
            'requested_at_utc' => [
                Rule::requiredIf($requestingNewTime),
                'nullable',
                'date',
                'after:now',
            ],
            'response_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->moveUtcErrorsToLocal($validator, 'requested_at_utc', 'requested_at_local', 'requested_timezone');
        });
    }

    /**
     * Get the custom validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'response.in' => __('Choose whether to accept, decline, or request a new time.'),
            'requested_at_local.required' => __('Choose a proposed date and time.'),
            'requested_at_local.date_format' => __('Enter a valid proposed date and time (YYYY-MM-DD HH:MM).'),
            'requested_timezone.required' => __('Choose a time zone for the proposed time.'),
            'requested_timezone.timezone' => __('Choose a valid time zone.'),
            'requested_at_utc.required' => __('Choose a valid future proposed date and time.'),
            'requested_at_utc.date' => __('Choose a valid future proposed date and time.'),
            'requested_at_utc.after' => __('The proposed date and time must be in the future.'),
        ];
    }

    /**
     * Get the validated data mapped for the interview service.
     *
     * @return array<string, mixed>
     */
    public function respondData(): array
    {
        $validated = $this->validated();

        return [
            'current_status' => (string) $validated['current_status'],
            'current_scheduled_at' => (string) $validated['current_scheduled_at'],
            'response' => (string) $validated['response'],
            'requested_at_utc' => $validated['requested_at_utc'] ?? null,
            'requested_timezone' => $validated['requested_timezone'] ?? null,
            'response_note' => $validated['response_note'] ?? null,
        ];
    }
}
