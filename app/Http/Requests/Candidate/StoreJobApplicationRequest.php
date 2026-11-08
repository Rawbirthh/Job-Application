<?php

namespace App\Http\Requests\Candidate;

use App\Enums\ScreeningQuestionType;
use App\Models\CandidateResume;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobScreeningQuestion;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'exists:job_screening_questions,id'],
            'answers.*.answer_text' => ['nullable', 'string'],
            'answers.*.answer_boolean' => ['nullable', 'boolean'],
            'answers.*.selected_option' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  Validator  $validator
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

            // Screening questions validation
            $activeQuestions = $job->screeningQuestions()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('id');

            $submitted = $this->input('answers', []);
            if (! is_array($submitted)) {
                $submitted = [];
            }

            // duplicate check
            $submittedIds = [];
            foreach ($submitted as $item) {
                if (is_array($item) && isset($item['question_id'])) {
                    $qid = (int) $item['question_id'];
                    if (in_array($qid, $submittedIds, true)) {
                        $validator->errors()->add('answers', __('Duplicate question answers are not allowed.'));

                        return;
                    }
                    $submittedIds[] = $qid;
                }
            }

            // unknown/inactive question check
            foreach ($submitted as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $qid = isset($item['question_id']) ? (int) $item['question_id'] : 0;
                if (! $activeQuestions->has($qid)) {
                    $validator->errors()->add("answers.{$index}.question_id", __('The selected question is invalid or inactive.'));

                    continue;
                }
            }

            // required questions must be answered
            foreach ($activeQuestions as $qid => $question) {
                if (! $question->is_required) {
                    continue;
                }
                $found = false;
                foreach ($submitted as $item) {
                    if (is_array($item) && isset($item['question_id']) && (int) $item['question_id'] === (int) $qid) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $validator->errors()->add('answers', __('A required screening question was not answered.'));
                }
            }

            // type-specific validation
            foreach ($submitted as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $qid = isset($item['question_id']) ? (int) $item['question_id'] : 0;
                if (! $activeQuestions->has($qid)) {
                    continue;
                }
                /** @var JobScreeningQuestion $question */
                $question = $activeQuestions->get($qid);

                if ($question->type === ScreeningQuestionType::ShortText) {
                    $this->validateShortText($validator, $index, $item, (bool) $question->is_required);
                } elseif ($question->type === ScreeningQuestionType::LongText) {
                    $this->validateLongText($validator, $index, $item, (bool) $question->is_required);
                } elseif ($question->type === ScreeningQuestionType::YesNo) {
                    $this->validateYesNo($validator, $index, $item, (bool) $question->is_required);
                } elseif ($question->type === ScreeningQuestionType::SingleChoice) {
                    $this->validateSingleChoice($validator, $index, $item, $question, (bool) $question->is_required);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateShortText(Validator $validator, int $index, array $item, bool $required): void
    {
        $value = $item['answer_text'] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $validator->errors()->add("answers.{$index}.answer_text", __('This field is required.'));
            }

            return;
        }
        if (! is_string($value)) {
            $validator->errors()->add("answers.{$index}.answer_text", __('Invalid answer.'));

            return;
        }
        $trimmed = trim($value);
        if (mb_strlen($trimmed) > 255) {
            $validator->errors()->add("answers.{$index}.answer_text", __('The answer may not be greater than 255 characters.'));
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateLongText(Validator $validator, int $index, array $item, bool $required): void
    {
        $value = $item['answer_text'] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $validator->errors()->add("answers.{$index}.answer_text", __('This field is required.'));
            }

            return;
        }
        if (! is_string($value)) {
            $validator->errors()->add("answers.{$index}.answer_text", __('Invalid answer.'));

            return;
        }
        $trimmed = trim($value);
        if (mb_strlen($trimmed) > 5000) {
            $validator->errors()->add("answers.{$index}.answer_text", __('The answer may not be greater than 5000 characters.'));
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateYesNo(Validator $validator, int $index, array $item, bool $required): void
    {
        $value = $item['answer_boolean'] ?? null;
        if ($value === null) {
            // maybe answer provided as text? require boolean if required
            if ($required) {
                $validator->errors()->add("answers.{$index}.answer_boolean", __('This field is required.'));
            }

            return;
        }
        if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1'], true)) {
            $validator->errors()->add("answers.{$index}.answer_boolean", __('The answer must be true or false.'));
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateSingleChoice(Validator $validator, int $index, array $item, JobScreeningQuestion $question, bool $required): void
    {
        $value = $item['selected_option'] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $validator->errors()->add("answers.{$index}.selected_option", __('This field is required.'));
            }

            return;
        }
        if (! is_string($value)) {
            $validator->errors()->add("answers.{$index}.selected_option", __('Invalid answer.'));

            return;
        }
        $options = $question->options ?? [];
        if (! in_array($value, $options, true)) {
            $validator->errors()->add("answers.{$index}.selected_option", __('The selected option is invalid.'));
        }
    }

    /**
     * Expose validated answers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function answersData(): array
    {
        $answers = $this->validated('answers') ?? $this->input('answers', []);
        if (! is_array($answers)) {
            return [];
        }

        $normalized = [];
        foreach ($answers as $item) {
            if (! is_array($item) || ! isset($item['question_id'])) {
                continue;
            }
            $entry = ['question_id' => (int) $item['question_id']];
            if (array_key_exists('answer_text', $item) && is_string($item['answer_text'])) {
                $entry['answer_text'] = trim($item['answer_text']);
                if ($entry['answer_text'] === '') {
                    $entry['answer_text'] = null;
                }
            } else {
                $entry['answer_text'] = $item['answer_text'] ?? null;
            }
            $entry['answer_boolean'] = array_key_exists('answer_boolean', $item) ? $this->toBoolean($item['answer_boolean']) : null;
            $entry['selected_option'] = array_key_exists('selected_option', $item) && is_string($item['selected_option']) ? trim($item['selected_option']) : ($item['selected_option'] ?? null);
            if ($entry['selected_option'] === '') {
                $entry['selected_option'] = null;
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    private function toBoolean(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1') {
            return true;
        }
        if ($value === 0 || $value === '0') {
            return false;
        }

        return null;
    }
}
