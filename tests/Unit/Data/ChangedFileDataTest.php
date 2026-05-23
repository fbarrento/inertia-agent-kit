<?php

declare(strict_types=1);

use InertiaAgentKit\Data\ChangedFileData;
use InertiaAgentKit\Enum\ChangedFileAction;
use InertiaAgentKit\Enum\ChangedFileRole;

test('serializes changed file entries with enum backed values', function (): void {
    $changedFile = new ChangedFileData(
        role: ChangedFileRole::Feature,
        action: ChangedFileAction::Modify,
        path: 'resources/js/features/vehicles/vehicle-table.tsx',
    );

    $encoded = json_encode($changedFile, JSON_THROW_ON_ERROR);
    $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

    expect($changedFile->role)->toBe(ChangedFileRole::Feature)
        ->and($changedFile->jsonSerialize())->toBe([
            'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
            'action' => 'modify',
        ])
        ->and($decoded)->toBe([
            'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
            'action' => 'modify',
        ]);
});
