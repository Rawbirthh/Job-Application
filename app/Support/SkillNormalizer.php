<?php

namespace App\Support;

/**
 * Normalises free-text skill names into a canonical, comparable form.
 *
 * The canonical form is the only thing written to the derived skill-index
 * tables. The candidate's and the employer's original spelling is kept in the
 * JSON `skills` column so it can still be displayed.
 */
class SkillNormalizer
{
    /**
     * Maximum length of a canonical skill.
     */
    public const MAX_LENGTH = 60;

    /**
     * Canonical aliases for common skill spellings.
     *
     * This is a normalisation dictionary, not a platform catalogue: it only
     * exists so that "Node JS", "nodejs" and "NodeJS" are treated as one skill.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'js' => 'javascript',
        'nodejs' => 'node.js',
        'node js' => 'node.js',
        'reactjs' => 'react.js',
        'react js' => 'react.js',
        'vuejs' => 'vue.js',
        'postgres' => 'postgresql',
        'golang' => 'go',
        'c#' => 'csharp',
        'c++' => 'cpp',
        'rest' => 'rest api',
        'restful' => 'rest api',
        'restful api' => 'rest api',
        'rest api' => 'rest api',
    ];

    /**
     * Normalise a single skill name.
     *
     * Trims, collapses internal whitespace, lower-cases, strips surrounding
     * punctuation, applies the alias map and truncates. Returns an empty string
     * when nothing usable remains.
     */
    public static function normalize(string $raw): string
    {
        $value = trim($raw);
        $value = (string) preg_replace('/\s+/u', ' ', $value);
        $value = mb_strtolower($value);
        $value = trim($value, " \t\n\r\0\x0B.,;:!?()[]{}\"'`");

        if ($value === '') {
            return '';
        }

        $value = self::ALIASES[$value] ?? $value;

        return mb_substr($value, 0, self::MAX_LENGTH);
    }

    /**
     * Normalise a list of skill names into a sorted, de-duplicated set.
     *
     * @param  array<int, mixed>  $raw
     * @return array<int, string>
     */
    public static function normalizeList(array $raw): array
    {
        $normalized = [];

        foreach ($raw as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $value = self::normalize($entry);

            if ($value === '') {
                continue;
            }

            $normalized[$value] = true;
        }

        $values = array_keys($normalized);

        sort($values, SORT_STRING);

        return $values;
    }
}
