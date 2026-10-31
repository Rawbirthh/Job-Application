<?php

namespace Database\Factories;

use App\Models\PayPeriod;
use App\Models\SalaryBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryBand>
 */
class SalaryBandFactory extends Factory
{
    protected $model = SalaryBand::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->jobTitle();

        // Generate non-overlapping default range for USD/month (most common)
        $lower = 0;
        $upper = 50000;

        return [
            'label' => $label,
            'currency' => 'USD',
            'pay_period_id' => PayPeriod::factory(),
            'lower_bound' => $lower,
            'upper_bound' => $upper,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the salary band is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the pay period for this band.
     */
    public function forPayPeriod(PayPeriod|int $payPeriod): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_period_id' => $payPeriod instanceof PayPeriod ? $payPeriod->id : $payPeriod,
        ]);
    }

    /**
     * Set the currency for this band.
     */
    public function currency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => strtoupper($currency),
        ]);
    }
}
