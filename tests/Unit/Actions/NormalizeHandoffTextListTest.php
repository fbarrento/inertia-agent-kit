<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\NormalizeHandoffTextList;

test('normalizes scalar list values', function (): void {
    $normalize = new NormalizeHandoffTextList;

    expect($normalize->handle([
        '  page:create:resources/js/pages/index.tsx  ',
        '',
        ' test:create:tests/Feature/IndexTest.php ',
    ]))->toBe([
        'page:create:resources/js/pages/index.tsx',
        'test:create:tests/Feature/IndexTest.php',
    ]);
});

test('drops non-scalar entries and booleans', function (): void {
    $normalize = new NormalizeHandoffTextList;

    expect($normalize->handle(['valid', ['nested'], true, 0, null, false]))->toBe(['valid', '0']);
});

test('returns an empty list for non-arrays', function (): void {
    $normalize = new NormalizeHandoffTextList;

    expect($normalize->handle('not-an-array'))->toBe([])
        ->and($normalize->handle(null))->toBe([])
        ->and($normalize->handle(123))->toBe([]);
});
