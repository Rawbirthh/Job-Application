<?php

namespace App\Models;

use App\Services\Recommendation\SkillIndexService;
use App\Support\LocationNormalizer;
use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string|null $location_city
 * @property array<int, string>|null $skills
 * @property int|null $preferred_category_id
 * @property int|null $preferred_experience_level_id
 * @property int|null $preferred_workplace_arrangement_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CandidateEducation> $educations
 * @property-read Collection<int, CandidateExperience> $experiences
 * @property-read Collection<int, CandidateSkill> $skillTags
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
        'preferred_category_id',
        'preferred_experience_level_id',
        'preferred_workplace_arrangement_id',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
        ];
    }

    /**
     * Keep the derived skill index in step with the profile's skill list.
     *
     * `created` and `updated` are used rather than `saved` because Laravel
     * only records the changed attributes for an update, so `wasChanged()` is
     * never true for a freshly inserted model.
     */
    protected static function booted(): void
    {
        static::created(function (self $profile): void {
            // A new profile has no indexed rows yet, so an empty skill list
            // needs no work at all.
            if ($profile->skills) {
                app(SkillIndexService::class)->syncProfile($profile);
            }
        });

        static::updated(function (self $profile): void {
            if ($profile->wasChanged('skills')) {
                app(SkillIndexService::class)->syncProfile($profile);
            }
        });
    }

    /**
     * Derive the comparable city token whenever the location is written.
     *
     * `location_city` is intentionally absent from `$fillable`, so it can only
     * ever be written through this mutator.
     *
     * @return Attribute<string|null, array<string, mixed>>
     */
    protected function location(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value,
            set: fn (?string $value): array => [
                'location' => $value,
                'location_city' => LocationNormalizer::city($value),
            ],
        );
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

    /**
     * The canonical skill index for this profile.
     *
     * Named `skillTags` because `skills` is already the JSON attribute.
     *
     * @return HasMany<CandidateSkill, $this>
     */
    public function skillTags(): HasMany
    {
        return $this->hasMany(CandidateSkill::class, 'candidate_profile_id');
    }

    /**
     * @return BelongsTo<JobCategory, $this>
     */
    public function preferredCategory(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'preferred_category_id');
    }

    /**
     * @return BelongsTo<ExperienceLevel, $this>
     */
    public function preferredExperienceLevel(): BelongsTo
    {
        return $this->belongsTo(ExperienceLevel::class, 'preferred_experience_level_id');
    }

    /**
     * @return BelongsTo<WorkplaceArrangement, $this>
     */
    public function preferredWorkplaceArrangement(): BelongsTo
    {
        return $this->belongsTo(WorkplaceArrangement::class, 'preferred_workplace_arrangement_id');
    }
}
