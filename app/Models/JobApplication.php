<?php

namespace App\Models;

use App\Enums\JobApplicationStatus;
use App\Models\Job as JobPosting;
use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_posting_id
 * @property int $user_id
 * @property int $candidate_resume_id
 * @property string|null $cover_letter
 * @property JobApplicationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobPosting $jobPosting
 * @property-read User $user
 * @property-read CandidateResume|null $candidateResume
 * @property-read Collection<int, JobApplicationStatusHistory> $statusHistories
 * @property-read Collection<int, Interview> $interviews
 * @property-read Collection<int, ApplicationNote> $notes
 * @property-read Collection<int, ApplicationEvaluation> $evaluations
 * @property-read ApplicationConversation|null $conversation
 */
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'job_posting_id',
        'user_id',
        'candidate_resume_id',
        'cover_letter',
        'status',
    ];

    protected $casts = [
        'status' => JobApplicationStatus::class,
    ];

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CandidateResume, $this>
     */
    public function candidateResume(): BelongsTo
    {
        return $this->belongsTo(CandidateResume::class);
    }

    /**
     * @return HasMany<JobApplicationStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(JobApplicationStatusHistory::class, 'job_application_id')->orderBy('created_at', 'asc');
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'job_application_id');
    }

    /**
     * @return HasMany<JobApplicationAnswer, $this>
     */
    public function screeningAnswers(): HasMany
    {
        return $this->hasMany(JobApplicationAnswer::class, 'job_application_id');
    }

    /**
     * @return HasOne<ApplicationConversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(ApplicationConversation::class, 'application_id');
    }

    /**
     * Private recruiter notes for this application, newest first.
     *
     * @return HasMany<ApplicationNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class, 'application_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Append-only recruiter evaluations, newest first.
     *
     * @return HasMany<ApplicationEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(ApplicationEvaluation::class, 'application_id')->orderByDesc('created_at')->orderByDesc('id');
    }
}
