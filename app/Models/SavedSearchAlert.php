<?php

namespace App\Models;

use App\Models\Job as JobPosting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $saved_search_id
 * @property int $job_posting_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SavedSearch $savedSearch
 * @property-read JobPosting $jobPosting
 */
class SavedSearchAlert extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'saved_search_id',
        'job_posting_id',
    ];

    /**
     * @return BelongsTo<SavedSearch, $this>
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }
}
