<?php

namespace App\Models;

use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $title
 * @property string $description
 * @property string|null $responsibilities
 * @property string|null $requirements
 * @property int $category_id
 * @property int $employment_type_id
 * @property int|null $workplace_arrangement_id
 * @property int|null $experience_level_id
 * @property numeric|null $salary_min
 * @property numeric|null $salary_max
 * @property string|null $salary_currency
 * @property bool $salary_negotiable
 * @property int|null $pay_period_id
 * @property string|null $location
 * @property Carbon|null $application_deadline
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    protected $table = 'job_postings';

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'responsibilities',
        'requirements',
        'category_id',
        'employment_type_id',
        'workplace_arrangement_id',
        'experience_level_id',
        'salary_min',
        'salary_max',
        'salary_currency',
        'salary_negotiable',
        'pay_period_id',
        'location',
        'application_deadline',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'salary_negotiable' => 'boolean',
            'application_deadline' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<JobCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<WorkplaceArrangement, $this>
     */
    public function workplaceArrangement(): BelongsTo
    {
        return $this->belongsTo(WorkplaceArrangement::class);
    }

    /**
     * @return BelongsTo<ExperienceLevel, $this>
     */
    public function experienceLevel(): BelongsTo
    {
        return $this->belongsTo(ExperienceLevel::class);
    }

    /**
     * @return BelongsTo<PayPeriod, $this>
     */
    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    /**
     * Scope a query to jobs for a specific company.
     *
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to jobs owned by the given user (through their companies).
     *
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    public function scopeForOwner(Builder $query, User $owner): Builder
    {
        return $query->whereHas('company', fn (Builder $q) => $q->where('owner_user_id', $owner->id));
    }

    /**
     * Scope a query to only draft jobs.
     *
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only pending_review jobs.
     *
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'pending_review');
    }

    /**
     * Scope a query to only published jobs.
     *
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope a query to only rejected jobs.
     *
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope a query to only closed jobs.
     *
     * @param  Builder<Job>  $query
     * @return Builder<Job>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    /**
     * @return HasMany<SavedJob, $this>
     */
    public function savedJobs(): HasMany
    {
        return $this->hasMany(SavedJob::class, 'job_posting_id');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_posting_id');
    }
}
