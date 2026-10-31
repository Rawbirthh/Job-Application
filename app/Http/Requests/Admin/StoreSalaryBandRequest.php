<?php

namespace App\Http\Requests\Admin;

use App\Rules\SalaryBandRangeDoesNotOverlap;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryBandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper(trim($this->input('currency', ''))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pay_period_id' => [
                'required',
                'integer',
                Rule::exists('pay_periods', 'id')->where('is_active', true),
            ],
            'label' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'lower_bound' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'upper_bound' => [
                'required',
                'numeric',
                'max:9999999999.99',
                'decimal:0,2',
                'gt:lower_bound',
                new SalaryBandRangeDoesNotOverlap(
                    currency: (string) $this->input('currency'),
                    payPeriodId: (int) $this->input('pay_period_id'),
                    lowerBound: (string) $this->input('lower_bound'),
                    upperBound: (string) $this->input('upper_bound'),
                ),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array{pay_period_id: int, label: string, currency: string, lower_bound: string, upper_bound: string, is_active: bool}
     */
    public function salaryBandData(): array
    {
        /** @var array{pay_period_id: int, label: string, currency: string, lower_bound: string, upper_bound: string, is_active?: bool} $data */
        $data = $this->safe()->all();

        return [
            'pay_period_id' => $data['pay_period_id'],
            'label' => $data['label'],
            'currency' => $data['currency'],
            'lower_bound' => $data['lower_bound'],
            'upper_bound' => $data['upper_bound'],
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
