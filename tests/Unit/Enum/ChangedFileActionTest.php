<?php

declare(strict_types=1);

use InertiaAgentKit\Enum\ChangedFileAction;

test('exposes changed file action values in JSON order', function (): void {
    expect(ChangedFileAction::values())->toBe([
        'create',
        'modify',
        'delete',
        'rename',
    ]);
});

test('parses changed file actions from JSON strings', function (): void {
    expect(ChangedFileAction::tryFrom('rename'))->toBe(ChangedFileAction::Rename)
        ->and(ChangedFileAction::tryFrom('unknown'))->toBeNull();
});
