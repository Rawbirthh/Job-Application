<?php

use App\Support\SkillNormalizer;

test('it trims, lower cases and collapses internal whitespace', function (): void {
    expect(SkillNormalizer::normalize('  PHP  '))->toBe('php');
    expect(SkillNormalizer::normalize('Laravel'))->toBe('laravel');
    expect(SkillNormalizer::normalize('Data   Science'))->toBe('data science');
});

test('it strips surrounding punctuation', function (): void {
    expect(SkillNormalizer::normalize('PHP,'))->toBe('php');
    expect(SkillNormalizer::normalize('"Laravel"'))->toBe('laravel');
    expect(SkillNormalizer::normalize('(React)'))->toBe('react');
});

test('it applies the alias map so equivalent spellings collapse', function (): void {
    expect(SkillNormalizer::normalize('JS'))->toBe('javascript');
    expect(SkillNormalizer::normalize('NodeJS'))->toBe('node.js');
    expect(SkillNormalizer::normalize('node js'))->toBe('node.js');
    expect(SkillNormalizer::normalize('ReactJS'))->toBe('react.js');
    expect(SkillNormalizer::normalize('Postgres'))->toBe('postgresql');
    expect(SkillNormalizer::normalize('Golang'))->toBe('go');
    expect(SkillNormalizer::normalize('C#'))->toBe('csharp');
    expect(SkillNormalizer::normalize('C++'))->toBe('cpp');
    expect(SkillNormalizer::normalize('REST'))->toBe('rest api');
    expect(SkillNormalizer::normalize('Restful'))->toBe('rest api');
});

test('it returns an empty string for unusable input', function (): void {
    expect(SkillNormalizer::normalize(''))->toBe('');
    expect(SkillNormalizer::normalize('   '))->toBe('');
    expect(SkillNormalizer::normalize('...'))->toBe('');
});

test('it truncates values to the maximum canonical length', function (): void {
    expect(SkillNormalizer::normalize(str_repeat('a', 100)))
        ->toBe(str_repeat('a', SkillNormalizer::MAX_LENGTH));
});

test('it normalises a list into a sorted de-duplicated set', function (): void {
    expect(SkillNormalizer::normalizeList(['PHP', 'php', ' Laravel ', 'JS', 'javascript', 'PHP']))
        ->toBe(['javascript', 'laravel', 'php']);
});

test('it ignores empty and non string entries in a list', function (): void {
    expect(SkillNormalizer::normalizeList(['PHP', '', '   ', 123, null, ['x']]))
        ->toBe(['php']);
});
