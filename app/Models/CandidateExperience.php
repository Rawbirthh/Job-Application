<?php

namespace App\Models;

use Database\Factories\CandidateExperienceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $candidate_profile_id
 * @property string $company_name
 * @property string $job_title
 * @property string|null $location
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $is_current
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CandidateExperience extends Model
{
    /** @use HasFactory<CandidateExperienceFactory> */
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'company_name',
        'job_title',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
