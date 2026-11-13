<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A canonical skill indexed against a job posting.
 *
 * @property int $id
 * @property int $job_posting_id
 * @property string $skill
 */
class JobSkill extends Model
{
    protected $fillable = [
        'job_posting_id',
        'skill',
    ];

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_posting_id');
    }
}
