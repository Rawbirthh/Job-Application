<?php

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Shared window resolution for the analytics index requests.
 *
 * The analytics metrics are all scoped to a date window. The window is
 * expressed as an inclusive pair of UTC calendar dates so that the same
 * source of truth drives the displayed filter and the executed query.
 */
trait ResolvesAnalyticsWindow
{
    /**
     * The largest number of days a single window may span.
     */
    protected const ANALYTICS_MAX_WINDOW_DAYS = 366;

    /**
     * The largest number of days in the future a window may end.
     */
    protected const ANALYTICS_MAX_FUTURE_DAYS = 31;

    /**
     * The window presets offered by the analytics filters.
     *
     * @var array<int, string>
     */
    protected const ANALYTICS_PRESETS = ['all', '7d', '30d', '90d'];

    /**
     * Trim the window input and normalize empty optional values to null.
     *
     * A non-string value is left untouched so the validation rules reject it
     * instead of silently nulling it.
     */
    protected function normalizeAnalyticsWindow(): void
    {
        $merged = [];

        foreach (['from', 'to', 'preset'] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            $merged[$field] = $value === '' ? null : $value;
        }

        $this->merge($merged);
    }

    /**
     * Get the validation rules that apply to the analytics window.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function analyticsWindowRules(): array
    {
        return [
            'from' => ['nullable', 'string', 'date_format:Y-m-d'],
            'to' => ['nullable', 'string', 'date_format:Y-m-d'],
            'preset' => ['nullable', 'string', Rule::in(self::ANALYTICS_PRESETS)],
        ];
    }

    /**
     * Run the cross-field checks that bound the window.
     */
    protected function validateAnalyticsWindow(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->analyticsFrom();
            $to = $this->analyticsTo();

            if ($from !== null && $from->lt(CarbonImmutable::today()->subDays(self::ANALYTICS_MAX_WINDOW_DAYS))) {
                $validator->errors()->add('from', __('The start date cannot be more than :days days in the past.', [
                    'days' => self::ANALYTICS_MAX_WINDOW_DAYS,
                ]));
            }

            if ($to !== null && $to->gt(CarbonImmutable::today()->addDays(self::ANALYTICS_MAX_FUTURE_DAYS))) {
                $validator->errors()->add('to', __('The end date cannot be more than :days days in the future.', [
                    'days' => self::ANALYTICS_MAX_FUTURE_DAYS,
                ]));
            }

            if ($from === null || $to === null) {
                return;
            }

            if ($from->gt($to)) {
                $validator->errors()->add('to', __('The end date must be on or after the start date.'));

                return;
            }

            if ($from->diffInDays($to) + 1 > self::ANALYTICS_MAX_WINDOW_DAYS) {
                $validator->errors()->add('to', __('The date range cannot span more than :days days.', [
                    'days' => self::ANALYTICS_MAX_WINDOW_DAYS,
                ]));
            }
        });
    }

    /**
     * Resolve the effective analytics window.
     *
     * An explicit date pair always wins and is reported as a custom window.
     * Otherwise the requested preset is expanded, and a request without any
     * window parameter falls back to the default thirty day window.
     *
     * @return array{from: ?CarbonImmutable, to: ?CarbonImmutable, preset: string}
     */
    public function analyticsWindow(): array
    {
        $from = $this->analyticsFrom();
        $to = $this->analyticsTo();

        if ($from !== null || $to !== null) {
            return ['from' => $from, 'to' => $to, 'preset' => 'custom'];
        }

        $today = CarbonImmutable::today();

        return match ($this->input('preset')) {
            'all' => ['from' => null, 'to' => null, 'preset' => 'all'],
            '7d' => ['from' => $today->subDays(6), 'to' => $today, 'preset' => '7d'],
            '90d' => ['from' => $today->subDays(89), 'to' => $today, 'preset' => '90d'],
            '30d' => ['from' => $today->subDays(29), 'to' => $today, 'preset' => '30d'],
            default => ['from' => $today->subDays(29), 'to' => $today, 'preset' => '30d'],
        };
    }

    /**
     * Read the requested window start as a UTC calendar date.
     */
    protected function analyticsFrom(): ?CarbonImmutable
    {
        return $this->parseAnalyticsDate($this->input('from'));
    }

    /**
     * Read the requested window end as a UTC calendar date.
     */
    protected function analyticsTo(): ?CarbonImmutable
    {
        return $this->parseAnalyticsDate($this->input('to'));
    }

    /**
     * Parse a `Y-m-d` calendar date, returning null for anything unusable.
     */
    private function parseAnalyticsDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
