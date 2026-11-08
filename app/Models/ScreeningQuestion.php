<?php

namespace App\Models;

use App\Enums\ScreeningQuestionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_user_id
 * @property ScreeningQuestionType $type
 * @property string $label
 * @property array<int, string>|null $options
 * @property bool $is_required
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 * @property-read Collection<int, ScreeningQuestionTemplate> $templates
 * @property-read Collection<int, JobScreeningQuestion> $jobQuestions
 */
class ScreeningQuestion extends Model
{
    //

    protected $fillable = [
        'owner_user_id',
        'type',
        'label',
        'options',
        'is_required',
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
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsToMany<ScreeningQuestionTemplate, $this>
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(ScreeningQuestionTemplate::class, 'screening_template_questions', 'screening_question_id', 'screening_question_template_id');
    }

    /**
     * @return HasMany<JobScreeningQuestion, $this>
     */
    public function jobQuestions(): HasMany
    {
        return $this->hasMany(JobScreeningQuestion::class, 'source_question_id');
    }

    /**
     * @param  Builder<ScreeningQuestion>  $query
     * @return Builder<ScreeningQuestion>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
