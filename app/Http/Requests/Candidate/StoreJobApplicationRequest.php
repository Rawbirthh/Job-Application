<?php

namespace App\Http\Requests\Candidate;

use App\Models\CandidateResume;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'candidate_resume_id' => [
                'required',
                'integer',
                'exists:candidate_resumes,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var CandidateResume|null $resume */
                    $resume = CandidateResume::query()->find($value);

                    if (! $resume || $resume->user_id !== $this->user()?->id) {
                        $fail(__('The selected resume is invalid or does not belong to you.'));
                    }
                },
            ],
            'cover_letter' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var Job|null $job */
            $job = $this->route('job');

            if (! $job) {
                $validator->errors()->add('job_posting_id', __('The selected job posting could not be found.'));

                return;
            }

            if ($job->status !== 'published') {
                $validator->errors()->add('job_posting_id', __('You can only apply to published job postings.'));
            }

            if ($job->application_deadline && $job->application_deadline->isPast()) {
                $validator->errors()->add('job_posting_id', __('This job application deadline has passed.'));
            }

            /** @var User|null $user */
            $user = $this->user();

            if ($user && JobApplication::query()->where('user_id', $user->id)->where('job_posting_id', $job->id)->exists()) {
                $validator->errors()->add('job_posting_id', __('You have already applied to this job posting.'));
            }
        });
    }
}
