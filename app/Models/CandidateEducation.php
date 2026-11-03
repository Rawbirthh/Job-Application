<?php

namespace App\Models;

use Database\Factories\CandidateEducationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $candidate_profile_id
 * @property string $institution
 * @property string|null $degree
 * @property string|null $field_of_study
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CandidateEducation extends Model
{
    /** @use HasFactory<CandidateEducationFactory> */
    use HasFactory;

    protected $table = 'candidate_educations';

    protected $fillable = [
        'candidate_profile_id',
        'institution',
        'degree',
        'field_of_study',
        'start_date',
        'end_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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
