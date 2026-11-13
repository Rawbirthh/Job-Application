<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A canonical skill indexed against a candidate profile.
 *
 * @property int $id
 * @property int $candidate_profile_id
 * @property string $skill
 */
class CandidateSkill extends Model
{
    protected $fillable = [
        'candidate_profile_id',
        'skill',
    ];

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'candidate_profile_id');
    }
}
