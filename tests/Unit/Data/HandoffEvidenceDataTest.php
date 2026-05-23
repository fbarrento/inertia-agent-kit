<?php

declare(strict_types=1);

use InertiaAgentKit\Data\ArtifactReferenceData;
use InertiaAgentKit\Data\HandoffEvidenceData;
use InertiaAgentKit\Enum\ArtifactKind;
use InertiaAgentKit\Enum\EvidenceStatus;

test('serializes handoff evidence using current audit tests verify and feedback keys', function (): void {
    $evidence = new HandoffEvidenceData(
        auditArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/audit.json',
            schema: 'iak.audit.v1',
        ),
        auditStatus: EvidenceStatus::Pending,
        testsArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/tests.json',
        ),
        testsStatus: EvidenceStatus::Passed,
        verifyArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/verify.json',
            schema: 'iak.verify.v1',
        ),
        feedbackUnresolved: 0,
    );

    expect($evidence->jsonSerialize())->toBe([
        'audit' => [
            'status' => 'pending',
            'artifact' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_01j/audit.json',
                'schema' => 'iak.audit.v1',
            ],
        ],
        'tests' => [
            'status' => 'passed',
            'artifact' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_01j/tests.json',
            ],
        ],
        'verify' => [
            'artifact' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_01j/verify.json',
                'schema' => 'iak.verify.v1',
            ],
        ],
        'feedback' => [
            'unresolved' => 0,
        ],
    ]);
});

test('serializes absent evidence values as null slots', function (): void {
    expect((new HandoffEvidenceData)->jsonSerialize())->toBe([
        'audit' => [
            'status' => null,
            'artifact' => null,
        ],
        'tests' => [
            'status' => null,
            'artifact' => null,
        ],
        'verify' => [
            'artifact' => null,
        ],
        'feedback' => [
            'unresolved' => null,
        ],
    ]);
});
