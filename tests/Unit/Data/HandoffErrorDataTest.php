<?php

declare(strict_types=1);

use InertiaAgentKit\Data\HandoffErrorData;

test('serializes handoff errors with nullable location and structured details', function (): void {
    $error = new HandoffErrorData(
        code: 'changed_file.invalid_action',
        message: 'Changed file action is not supported.',
        details: [
            'index' => 0,
            'entry' => 'feature:touch:resources/js/features/vehicles/table.tsx',
            'allowed' => ['create', 'modify', 'delete', 'rename'],
        ],
    );

    expect($error->jsonSerialize())->toBe([
        'code' => 'changed_file.invalid_action',
        'message' => 'Changed file action is not supported.',
        'file' => null,
        'line' => null,
        'details' => [
            'index' => 0,
            'entry' => 'feature:touch:resources/js/features/vehicles/table.tsx',
            'allowed' => ['create', 'modify', 'delete', 'rename'],
        ],
    ]);
});
