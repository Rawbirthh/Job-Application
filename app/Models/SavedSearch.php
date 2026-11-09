<?php

namespace App\Models;

use App\Enums\AlertFrequency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $name
 * @property string|null $search
 * @property int|null $category_id
 * @property int|null $employment_type_id
 * @property int|null $workplace_arrangement_id
 * @property int|null $experience_level_id
 * @property bool $alerts_enabled
 * @property AlertFrequency $alert_frequency
 * @property CarbonImmutable|null $last_alerted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read JobCategory|null $category
 * @property-read EmploymentType|null $employmentType
 * @property-read WorkplaceArrangement|null $workplaceArrangement
 * @property-read ExperienceLevel|null $experienceLevel
 */
class SavedSearch extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'search',
        'category_id',
        'employment_type_id',
        'workplace_arrangement_id',
        'experience_level_id',
        'alerts_enabled',
        'alert_frequency',
    ];

    protected function casts(): array
    {
        return [
            'alerts_enabled' => 'boolean',
            'alert_frequency' => AlertFrequency::class,
            'last_alerted_at' => 'datetime',
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
     * @return BelongsTo<JobCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class);
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
     * @return HasMany<SavedSearchAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(SavedSearchAlert::class, 'saved_search_id');
    }

    /**
     * Return the public listing filter-key map for rerun URLs and alert matching.
     *
     * @return array{search: string|null, category: int|null, employment_type: int|null, workplace_arrangement: int|null, experience_level: int|null}
     */
    public function criteria(): array
    {
        return [
            'search' => ($this->search ?? '') !== '' ? $this->search : null,
            'category' => $this->category_id,
            'employment_type' => $this->employment_type_id,
            'workplace_arrangement' => $this->workplace_arrangement_id,
            'experience_level' => $this->experience_level_id,
        ];
    }
}
