<?php

namespace App\Http\Requests\Candidate;

use App\Models\Job;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SaveJobRequest extends FormRequest
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
            'job_posting_id' => [
                'required',
                'integer',
                'exists:job_postings,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var Job|null $job */
                    /** @var Job|null $job */
                    $job = Job::query()->find($value);

                    if (! $job || $job->status !== 'published') {
                        $fail(__('Save only visible published jobs.'));
                    }
                },
            ],
        ];
    }
}
