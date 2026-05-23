<?php

declare(strict_types=1);

use InertiaAgentKit\Data\ArtifactReferenceData;
use InertiaAgentKit\Enum\ArtifactKind;

test('serializes artifact references with optional schema and status metadata', function (): void {
    $artifact = new ArtifactReferenceData(
        kind: ArtifactKind::Json,
        path: '.iak/runs/run_01j/handoff.json',
        schema: 'iak.handoff.v1',
        status: 'not_written',
    );

    expect($artifact->jsonSerialize())->toBe([
        'kind' => 'json',
        'path' => '.iak/runs/run_01j/handoff.json',
        'schema' => 'iak.handoff.v1',
        'status' => 'not_written',
    ]);
});

test('omits absent optional artifact metadata', function (): void {
    $artifact = new ArtifactReferenceData(
        kind: ArtifactKind::Screenshot,
        path: '.iak/runs/run_01j/screenshots/vehicles-index.png',
    );

    expect($artifact->jsonSerialize())->toBe([
        'kind' => 'screenshot',
        'path' => '.iak/runs/run_01j/screenshots/vehicles-index.png',
    ]);
});
