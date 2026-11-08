<?php

namespace App\Http\Requests\Employer;

use App\Enums\InterviewMode;
use App\Http\Requests\Concerns\ConvertsLocalDateTimeToUtc;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInterviewRequest extends FormRequest
{
    use ConvertsLocalDateTimeToUtc;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('application');
        $user = $this->user();

        return $application instanceof JobApplication
            && $user instanceof User
            && $user->can('createForApplication', [Interview::class, $application]);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeStringFields(
            ['scheduled_at_local', 'timezone', 'mode', 'location_details', 'candidate_instructions', 'internal_notes'],
            ['candidate_instructions', 'internal_notes'],
        );

        $this->convertLocalDateTimeToUtc('scheduled_at_local', 'timezone', 'scheduled_at_utc');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_at_local' => ['required', 'string', 'date_format:Y-m-d\TH:i'],
            'timezone' => ['required', 'string', 'timezone'],
            'scheduled_at_utc' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'between:15,480'],
            'mode' => ['required', 'string', Rule::enum(InterviewMode::class)],
            'location_details' => ['required', 'string', 'max:500'],
            'candidate_instructions' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->moveUtcErrorsToLocal($validator, 'scheduled_at_utc', 'scheduled_at_local', 'timezone');
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
            'scheduled_at_local.required' => __('Choose the interview date and time.'),
            'scheduled_at_local.date_format' => __('Enter a valid interview date and time (YYYY-MM-DD HH:MM).'),
            'timezone.required' => __('Choose a time zone for the interview.'),
            'timezone.timezone' => __('Choose a valid time zone.'),
            'scheduled_at_utc.required' => __('Choose a valid future interview date and time.'),
            'scheduled_at_utc.date' => __('Choose a valid future interview date and time.'),
            'scheduled_at_utc.after' => __('The interview date and time must be in the future.'),
            'duration_minutes.between' => __('The interview duration must be between 15 and 480 minutes.'),
            'mode.enum' => __('Choose a valid interview mode.'),
        ];
    }

    /**
     * Get the validated data mapped for the interview service.
     *
     * @return array<string, mixed>
     */
    public function scheduleData(): array
    {
        $validated = $this->validated();

        return [
            'scheduled_at_utc' => (string) $validated['scheduled_at_utc'],
            'duration_minutes' => (int) $validated['duration_minutes'],
            'timezone' => (string) $validated['timezone'],
            'mode' => (string) $validated['mode'],
            'location_details' => (string) $validated['location_details'],
            'candidate_instructions' => $validated['candidate_instructions'] ?? null,
            'internal_notes' => $validated['internal_notes'] ?? null,
        ];
    }
}
