<?php

namespace App\Support;

/**
 * Normalises a free-text location into a comparable city token.
 *
 * The token is derived on write and stored alongside the original free-text
 * value so that location matching is exact, indexable and identical on every
 * database engine.
 */
class LocationNormalizer
{
    /**
     * Maximum length of the derived city token.
     */
    public const MAX_LENGTH = 100;

    /**
     * Values that describe "no particular location".
     *
     * @var array<int, string>
     */
    private const LOCATION_AGNOSTIC = [
        'remote',
        'anywhere',
        'worldwide',
        'n/a',
        'work from home',
        'wfh',
        '-',
        '--',
        '—',
    ];

    /**
     * Derive the city token from a free-text location.
     *
     * Returns null when the location is empty or describes no particular place.
     */
    public static function city(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        $segment = explode(',', $value)[0];
        $segment = (string) preg_replace('/\s+/u', ' ', $segment);
        $segment = mb_strtolower($segment);
        $segment = trim($segment);

        if ($segment === '' || in_array($segment, self::LOCATION_AGNOSTIC, true)) {
            return null;
        }

        $segment = (string) preg_replace('/[^\p{L}\p{N}\s-]/u', '', $segment);
        $segment = trim($segment);
        $segment = (string) preg_replace('/\s+/u', ' ', $segment);

        if ($segment === '' || in_array($segment, self::LOCATION_AGNOSTIC, true)) {
            return null;
        }

        return mb_substr($segment, 0, self::MAX_LENGTH);
    }
}
