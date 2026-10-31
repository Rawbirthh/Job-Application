<?php

namespace App\Models;

use Database\Factories\PayPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayPeriod extends Model
{
    /** @use HasFactory<PayPeriodFactory> */
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
     * Scope a query to only include active pay periods.
     *
     * @param  Builder<PayPeriod>  $query
     * @return Builder<PayPeriod>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the salary bands that reference this pay period.
     *
     * @return HasMany<SalaryBand, $this>
     */
    public function salaryBands(): HasMany
    {
        return $this->hasMany(SalaryBand::class);
    }
}
