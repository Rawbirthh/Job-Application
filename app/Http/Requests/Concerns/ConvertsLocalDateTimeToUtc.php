<?php

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

trait ConvertsLocalDateTimeToUtc
{
    /**
     * Trim the given string fields and normalize empty optional values to null.
     *
     * @param  array<int, string>  $fields
     * @param  array<int, string>  $nullableFields
     */
    protected function normalizeStringFields(array $fields, array $nullableFields = []): void
    {
        $merged = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if (in_array($field, $nullableFields, true) && $value === '') {
                $value = null;
            }

            $merged[$field] = $value;
        }

        $this->merge($merged);
    }

    /**
     * Convert a local wall-clock value in the selected IANA time zone to a UTC instant.
     *
     * The server-owned UTC field is always overwritten so client supplied instants
     * are never trusted.
     */
    protected function convertLocalDateTimeToUtc(string $localField, string $timezoneField, string $utcField): void
    {
        $this->merge([$utcField => null]);

        $local = $this->input($localField);
        $timezone = $this->input($timezoneField);

        if (! is_string($local) || ! is_string($timezone) || $local === '' || $timezone === '') {
            return;
        }

        try {
            $zone = new DateTimeZone($timezone);
        } catch (\Throwable) {
            return;
        }

        try {
            $localInstant = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $local, $zone);

            if (! $localInstant instanceof CarbonImmutable || $localInstant->format('Y-m-d\TH:i') !== $local) {
                return;
            }

            $this->merge([$utcField => $localInstant->utc()->format('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            return;
        }
    }

    /**
     * Move server-owned UTC validation failures onto the local date/time field so
     * clients never interact with the derived UTC value.
     */
    protected function moveUtcErrorsToLocal(
        Validator $validator,
        string $utcField,
        string $localField,
        string $timezoneField,
    ): void {
        if (! $validator->errors()->has($utcField)) {
            return;
        }

        if ($validator->errors()->has($localField) || $validator->errors()->has($timezoneField)) {
            $validator->errors()->forget($utcField);

            return;
        }

        $messages = Arr::wrap($validator->errors()->get($utcField));
        $validator->errors()->forget($utcField);

        foreach ($messages as $message) {
            if (is_string($message)) {
                $validator->errors()->add($localField, $message);
            }
        }
    }
}
