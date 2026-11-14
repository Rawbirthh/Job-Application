<?php

namespace App\Models;

use Database\Factories\ApplicationEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only recruiter evaluation record for a job application.
 * No update/delete routes exist; corrections are new entries.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $evaluator_id
 * @property int $relevant_experience
 * @property int $technical_skills
 * @property int $communication
 * @property int $interview_performance
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobApplication $application
 * @property-read User|null $evaluator
 */
class ApplicationEvaluation extends Model
{
    /** @use HasFactory<ApplicationEvaluationFactory> */
    use HasFactory;

    protected $fillable = [
        'application_id',
        'evaluator_id',
        'relevant_experience',
        'technical_skills',
        'communication',
        'interview_performance',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'relevant_experience' => 'integer',
            'technical_skills' => 'integer',
            'communication' => 'integer',
            'interview_performance' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
