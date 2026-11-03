<?php

namespace App\Models;

use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $summary
 * @property string|null $location
 * @property array<int, string>|null $skills
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CandidateEducation> $educations
 * @property-read Collection<int, CandidateExperience> $experiences
 * @property-read User $user
 */
class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'headline',
        'summary',
        'location',
        'skills',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CandidateEducation, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class)->orderBy('id');
    }

    /**
     * @return HasMany<CandidateExperience, $this>
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class)->orderBy('id');
    }
}
