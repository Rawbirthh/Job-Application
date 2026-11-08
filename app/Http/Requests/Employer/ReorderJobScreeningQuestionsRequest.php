<?php

namespace App\Http\Requests\Employer;

use App\Models\Job;
use App\Models\JobScreeningQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderJobScreeningQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Job|null $job */
            $job = $this->route('job');

            if (! $job) {
                $validator->errors()->add('job', __('The selected job could not be found.'));

                return;
            }

            $ids = $this->input('question_ids');
            if (! is_array($ids)) {
                return;
            }

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('question_ids', __('Duplicate questions are not allowed.'));

                return;
            }

            $ownedCount = JobScreeningQuestion::query()
                ->where('job_posting_id', $job->id)
                ->whereIn('id', $ids)
                ->count();

            if ($ownedCount !== count($ids)) {
                $validator->errors()->add('question_ids', __('One or more questions are invalid for this job.'));
            }
        });
    }

    /**
     * @return array<int, int>
     */
    public function questionIds(): array
    {
        return array_values($this->validated()['question_ids']);
    }
}
