<?php

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportResolutionAction;
use App\Enums\ReportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $reporter_id
 * @property int|null $job_posting_id
 * @property int|null $company_id
 * @property ReportReason $reason
 * @property string $subject_key
 * @property string|null $explanation
 * @property ReportStatus $status
 * @property ReportResolutionAction|null $resolution_action
 * @property string|null $resolution_message
 * @property string|null $internal_note
 * @property int|null $resolved_by_id
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $open_marker
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $reporter
 * @property-read Job|null $jobPosting
 * @property-read Company|null $company
 * @property-read User|null $resolvedBy
 */
class Report extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporter_id',
        'job_posting_id',
        'company_id',
        'subject_key',
        'reason',
        'explanation',
        'status',
        'resolution_action',
        'resolution_message',
        'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolution_action' => ReportResolutionAction::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_posting_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    /**
     * Build the server-managed, non-null dedupe key for a report subject.
     */
    public static function subjectKeyFor(?int $jobPostingId, ?int $companyId): string
    {
        if ($companyId !== null) {
            return "company:{$companyId}";
        }

        return "job:{$jobPostingId}";
    }

    /**
     * Scope a query to reports that are pending or under investigation.
     *
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ReportStatus::openValues());
    }
}
