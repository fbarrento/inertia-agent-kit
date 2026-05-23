<?php

declare(strict_types=1);

use InertiaAgentKit\Data\HandoffMetaData;

test('serializes handoff create meta with requested input details', function (): void {
    $meta = new HandoffMetaData(
        createdAt: '2026-05-22T15:00:00+00:00',
        package: 'fbarrento/inertia-agent-kit',
        iakVersion: '0.1.0',
        mode: 'first_port',
        requestedStatus: 'completed',
        requestedChangedFiles: [
            'feature:modify:resources/js/features/vehicles/vehicle-table.tsx',
        ],
        hasGroupedChangedFiles: true,
    );

    expect($meta->jsonSerialize())->toBe([
        'createdAt' => '2026-05-22T15:00:00+00:00',
        'package' => 'fbarrento/inertia-agent-kit',
        'iakVersion' => '0.1.0',
        'mode' => 'first_port',
        'requested' => [
            'status' => 'completed',
            'changedFile' => [
                'feature:modify:resources/js/features/vehicles/vehicle-table.tsx',
            ],
            'hasGroupedChangedFiles' => true,
        ],
    ]);
});

test('serializes handoff validation meta with source details', function (): void {
    $meta = new HandoffMetaData(
        createdAt: '2026-05-22T15:00:00+00:00',
        package: 'fbarrento/inertia-agent-kit',
        iakVersion: '0.1.0',
        source: 'validator',
    );

    expect($meta->jsonSerialize())->toBe([
        'createdAt' => '2026-05-22T15:00:00+00:00',
        'package' => 'fbarrento/inertia-agent-kit',
        'iakVersion' => '0.1.0',
        'source' => 'validator',
    ]);
});
