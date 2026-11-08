<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ScreeningQuestionTemplate extends Model
{
    //

    protected $fillable = [
        'owner_user_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
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
     * @return BelongsToMany<ScreeningQuestion, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(ScreeningQuestion::class, 'screening_template_questions', 'screening_question_template_id', 'screening_question_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /**
     * @param  Builder<ScreeningQuestionTemplate>  $query
     * @return Builder<ScreeningQuestionTemplate>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
