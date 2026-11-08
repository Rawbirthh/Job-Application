<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobScreeningQuestion;
use App\Models\ScreeningQuestionTemplate;
use Illuminate\Support\Facades\DB;

class ScreeningTemplateService
{
    public function applyToJob(Job $job, ScreeningQuestionTemplate $template): void
    {
        DB::transaction(function () use ($job, $template): void {
            $existingCount = $job->screeningQuestions()->count();
            $templateQuestions = $template->questions()->get();

            $sortOrder = $existingCount;
            foreach ($templateQuestions as $question) {
                JobScreeningQuestion::create([
                    'job_posting_id' => $job->id,
                    'source_question_id' => $question->id,
                    'type' => $question->type->value,
                    'label' => $question->label,
                    'options' => $question->options,
                    'is_required' => $question->is_required,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]);
                $sortOrder++;
            }

            $index = 0;
            foreach ($job->screeningQuestions()->orderBy('sort_order')->orderBy('id')->get() as $jobQuestion) {
                $jobQuestion->sort_order = $index++;
                $jobQuestion->save();
            }
        });
    }
}
