<?php

namespace App\Models;

use Database\Factories\ExperienceLevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExperienceLevel extends Model
{
    /** @use HasFactory<ExperienceLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Scope a query to only include active experience levels.
     *
     * @param  Builder<ExperienceLevel>  $query
     * @return Builder<ExperienceLevel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
