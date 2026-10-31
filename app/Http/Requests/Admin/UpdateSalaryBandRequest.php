<?php

namespace App\Http\Requests\Admin;

use App\Models\PayPeriod;
use App\Models\SalaryBand;
use App\Rules\SalaryBandRangeDoesNotOverlap;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryBandRequest extends FormRequest
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
        $bandId = $this->route('salary_band');

        return [
            'pay_period_id' => [
                'required',
                'integer',
                Rule::exists('pay_periods', 'id'),
            ],
            'label' => [
                'required',
                'string',
                'max:100',
                Rule::unique('salary_bands', 'label')
                    ->where(function ($query) {
                        $query->where('currency', $this->input('currency'))
                            ->where('pay_period_id', $this->input('pay_period_id'));
                    })
                    ->ignore($bandId),
            ],
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
                    ignoreBandId: (int) $bandId,
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

    public function messages(): array
    {
        return [
            'pay_period_id.exists' => 'The selected pay period does not exist or is inactive.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $bandId = (int) $this->route('salary_band');
            $newPayPeriodId = (int) $this->input('pay_period_id');

            $currentBand = SalaryBand::query()->find($bandId);
            if ($currentBand && $currentBand->pay_period_id !== $newPayPeriodId) {
                $payPeriod = PayPeriod::query()->find($newPayPeriodId);
                if ($payPeriod && ! $payPeriod->is_active) {
                    $validator->errors()->add('pay_period_id', 'The selected pay period is inactive and cannot be used for new bands.');
                }
            }
        });
    }
}
