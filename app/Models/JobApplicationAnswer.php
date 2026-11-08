<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicationAnswer extends Model
{
    //

    protected $fillable = [
        'job_application_id',
        'job_screening_question_id',
        'answer_text',
        'answer_boolean',
        'selected_option',
    ];

    protected function casts(): array
    {
        return [
            'answer_boolean' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /**
     * @return BelongsTo<JobScreeningQuestion, $this>
     */
    public function jobScreeningQuestion(): BelongsTo
    {
        return $this->belongsTo(JobScreeningQuestion::class, 'job_screening_question_id');
    }
}
