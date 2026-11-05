<?php

namespace App\Models;

use App\Models\Job as JobPosting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_posting_id
 * @property int $user_id
 * @property int $candidate_resume_id
 * @property string|null $cover_letter
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JobPosting $jobPosting
 * @property-read User $user
 * @property-read CandidateResume $candidateResume
 */
class JobApplication extends Model
{
    /** @use HasFactory */
    use HasFactory;

    protected $fillable = [
        'job_posting_id',
        'user_id',
        'candidate_resume_id',
        'cover_letter',
        'status',
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
}
