<?php

namespace App\Models;

use Database\Factories\WorkplaceArrangementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkplaceArrangement extends Model
{
    /** @use HasFactory<WorkplaceArrangementFactory> */
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
     * Scope a query to only include active workplace arrangements.
     *
     * @param  Builder<WorkplaceArrangement>  $query
     * @return Builder<WorkplaceArrangement>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
