<?php

namespace App\Rules;

use App\Models\SalaryBand;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SalaryBandRangeDoesNotOverlap implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(
        private readonly string $currency,
        private readonly int $payPeriodId,
        private readonly string $lowerBound,
        private readonly string $upperBound,
        private readonly ?int $ignoreBandId = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Guard against non-numeric inputs - this rule runs after numeric/decimal validation
        if (! is_numeric($this->lowerBound) || ! is_numeric($this->upperBound)) {
            return;
        }

        $query = SalaryBand::query()
            ->where('currency', $this->currency)
            ->where('pay_period_id', $this->payPeriodId)
            ->where('lower_bound', '<', $this->upperBound)
            ->where('upper_bound', '>', $this->lowerBound);

        if ($this->ignoreBandId !== null) {
            $query->where('id', '!=', $this->ignoreBandId);
        }

        if ($query->exists()) {
            $fail('This range overlaps an existing salary band for the selected currency and pay period.');
        }
    }
}
