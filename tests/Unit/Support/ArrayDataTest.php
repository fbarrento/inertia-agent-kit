<?php

declare(strict_types=1);

use InertiaAgentKit\Support\ArrayData;

test('maps only string keys from associative arrays', function (): void {
    expect(ArrayData::stringMap(['title' => 'app', 12 => 'numeric']))->toBe([
        'title' => 'app',
    ]);
});

test('returns empty map for non-associative arrays and non-arrays', function (): void {
    expect(ArrayData::stringMap(['a', 'b']))->toBe([])
        ->and(ArrayData::stringMap('invalid'))->toBe([]);
});

test('extracts a nested string-keyed map by path', function (): void {
    $source = [
        'meta' => [
            'name' => 'handoff',
            'level' => 2,
        ],
    ];

    expect(ArrayData::stringMapAt($source, ['meta']))->toBe([
        'name' => 'handoff',
        'level' => 2,
    ]);
});

test('extracts a list of string maps and filters invalid entries', function (): void {
    expect(ArrayData::stringMapList([
        ['name' => 'alpha'],
        ['name' => 'beta', '0' => 'skip'],
        'not-array',
    ]))->toBe([
        ['name' => 'alpha'],
        ['name' => 'beta'],
    ]);
});

test('returns empty string map list for scalar or non-list input', function (): void {
    expect(ArrayData::stringMapList(['name' => 'value']))->toBe([])
        ->and(ArrayData::stringMapList('invalid'))->toBe([]);
});

test('extracts strings with default fallback behavior', function (): void {
    $source = ['meta' => ['name' => 'handoff', 'empty' => '']];

    expect(ArrayData::stringAt($source, ['meta', 'name'], 'default'))->toBe('handoff')
        ->and(ArrayData::stringAt($source, ['meta', 'missing'], 'default'))->toBe('default')
        ->and(ArrayData::stringAt($source, ['meta', 'empty'], 'default'))->toBe('default');
});

test('extracts integers with fallback for missing or non-integer values', function (): void {
    $source = ['meta' => ['count' => 2, 'name' => '2']];

    expect(ArrayData::intAt($source, ['meta', 'count'], 10))->toBe(2)
        ->and(ArrayData::intAt($source, ['meta', 'name'], 10))->toBe(10)
        ->and(ArrayData::intAt($source, ['meta', 'missing'], 10))->toBe(10);
});

test('navigates nested array structures safely', function (): void {
    $source = ['meta' => ['nested' => ['value' => 8]]];

    expect(ArrayData::valueAt($source, ['meta', 'nested', 'value']))->toBe(8)
        ->and(ArrayData::valueAt($source, ['meta', 'nested', 'missing']))->toBeNull()
        ->and(ArrayData::valueAt($source, ['meta', 'nested', 'value', 'extra']))->toBeNull()
        ->and(ArrayData::valueAt(['meta' => 'scalar'], ['meta', 'nested']))->toBeNull();
});
