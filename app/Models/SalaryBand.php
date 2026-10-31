<?php

namespace App\Models;

use Database\Factories\SalaryBandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryBand extends Model
{
    /** @use HasFactory<SalaryBandFactory> */
    use HasFactory;

    protected $fillable = [
        'label',
        'currency',
        'pay_period_id',
        'lower_bound',
        'upper_bound',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
        ];
    }

    /**
     * Scope a query to only include active salary bands.
     *
     * @param  Builder<SalaryBand>  $query
     * @return Builder<SalaryBand>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the pay period that this salary band belongs to.
     *
     * @return BelongsTo<PayPeriod, $this>
     */
    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }
}
