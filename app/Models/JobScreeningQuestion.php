<?php

namespace App\Models;

use App\Enums\ScreeningQuestionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_posting_id
 * @property int|null $source_question_id
 * @property ScreeningQuestionType $type
 * @property string $label
 * @property array<int, string>|null $options
 * @property bool $is_required
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Job $jobPosting
 * @property-read ScreeningQuestion|null $sourceQuestion
 * @property-read Collection<int, JobApplicationAnswer> $answers
 */
class JobScreeningQuestion extends Model
{
    //

    protected $fillable = [
        'job_posting_id',
        'source_question_id',
        'type',
        'label',
        'options',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ScreeningQuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<ScreeningQuestion, $this>
     */
    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(ScreeningQuestion::class, 'source_question_id');
    }

    /**
     * @return HasMany<JobApplicationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(JobApplicationAnswer::class, 'job_screening_question_id');
    }

    /**
     * @param  Builder<JobScreeningQuestion>  $query
     * @return Builder<JobScreeningQuestion>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
