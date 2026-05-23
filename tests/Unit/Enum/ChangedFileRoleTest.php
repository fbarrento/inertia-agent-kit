<?php

declare(strict_types=1);

use InertiaAgentKit\Enum\ChangedFileRole;

test('exposes changed file role values in JSON order', function (): void {
    expect(ChangedFileRole::values())->toBe([
        'page',
        'feature',
        'story',
        'component-ui',
        'component-app',
        'layout',
        'type',
        'config',
        'test',
        'docs',
        'boost',
        'package',
        'resource',
        'other',
    ]);
});

test('parses changed file roles from JSON strings', function (): void {
    expect(ChangedFileRole::tryFrom('component-ui'))->toBe(ChangedFileRole::ComponentUi)
        ->and(ChangedFileRole::tryFrom('unknown'))->toBeNull();
});
