<?php

namespace App\Http\Requests\Employer;

use App\Models\Job;
use App\Models\ScreeningQuestionTemplate;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApplyScreeningTemplateRequest extends FormRequest
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
            'template_id' => ['required', 'integer', 'exists:screening_question_templates,id'],
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

            /** @var User|null $user */
            $user = $this->user();

            /** @var ScreeningQuestionTemplate|null $template */
            $template = ScreeningQuestionTemplate::query()->find($this->input('template_id'));
            if (! $template || ! $user || $template->owner_user_id !== $user->id) {
                $validator->errors()->add('template_id', __('The selected template is invalid.'));

                return;
            }

            if ($job->screeningQuestions()->count() + $template->questions()->count() > 50) {
                $validator->errors()->add('template_id', __('Applying this template would exceed the maximum of 50 questions per job.'));
            }
        });
    }

    public function templateId(): int
    {
        return (int) $this->validated()['template_id'];
    }
}
