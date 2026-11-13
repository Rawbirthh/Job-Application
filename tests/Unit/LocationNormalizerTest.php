<?php

use App\Support\LocationNormalizer;

test('it keeps only the segment before the first comma', function (): void {
    expect(LocationNormalizer::city('Pune, India'))->toBe('pune');
    expect(LocationNormalizer::city('Bengaluru, Karnataka, India'))->toBe('bengaluru');
    expect(LocationNormalizer::city('Pune'))->toBe('pune');
});

test('it lower cases and strips punctuation', function (): void {
    expect(LocationNormalizer::city('  SAN  FRANCISCO '))->toBe('san francisco');
    expect(LocationNormalizer::city('New York.'))->toBe('new york');
});

test('it returns null for location agnostic values', function (): void {
    expect(LocationNormalizer::city('Remote'))->toBeNull();
    expect(LocationNormalizer::city('  REMOTE '))->toBeNull();
    expect(LocationNormalizer::city('Anywhere'))->toBeNull();
    expect(LocationNormalizer::city('Worldwide'))->toBeNull();
    expect(LocationNormalizer::city('N/A'))->toBeNull();
    expect(LocationNormalizer::city('Work From Home'))->toBeNull();
    expect(LocationNormalizer::city('wfh'))->toBeNull();
    expect(LocationNormalizer::city('-'))->toBeNull();
});

test('it returns null for empty input', function (): void {
    expect(LocationNormalizer::city(null))->toBeNull();
    expect(LocationNormalizer::city(''))->toBeNull();
    expect(LocationNormalizer::city('   '))->toBeNull();
});
