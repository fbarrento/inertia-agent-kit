<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\NormalizeHandoffText;

test('returns trimmed text from scalar values', function (): void {
    $normalize = new NormalizeHandoffText;

    expect($normalize->handle('  hello world  '))->toBe('hello world')
        ->and($normalize->handle("\tabc\n"))->toBe('abc')
        ->and($normalize->handle(123))->toBe('123');
});

test('returns fallback for null, empty, or boolean values', function (): void {
    $normalize = new NormalizeHandoffText;

    expect($normalize->handle(null))->toBeNull()
        ->and($normalize->handle(''))->toBeNull()
        ->and($normalize->handle('   '))->toBeNull()
        ->and($normalize->handle(false, 'none'))->toBe('none')
        ->and($normalize->handle(true, 'yes'))->toBe('yes');
});

test('returns custom fallback when provided', function (): void {
    $normalize = new NormalizeHandoffText;

    expect($normalize->handle('', 'fallback'))->toBe('fallback')
        ->and($normalize->handle('  42  ', 'fallback'))->toBe('42');
});
