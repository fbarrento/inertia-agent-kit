<?php

declare(strict_types=1);

use InertiaAgentKit\Data\ArtifactReferenceData;
use InertiaAgentKit\Data\ChangedFileData;
use InertiaAgentKit\Data\CreateHandoffData;
use InertiaAgentKit\Data\NextActionData;
use InertiaAgentKit\Enum\ArtifactKind;
use InertiaAgentKit\Enum\ChangedFileAction;
use InertiaAgentKit\Enum\ChangedFileRole;
use InertiaAgentKit\Enum\HandoffStatus;

test('serializes typed create handoff input to the normalized creator request shape', function (): void {
    $request = new CreateHandoffData(
        task: 'Create vehicle index page',
        status: HandoffStatus::Completed,
        summary: 'Vehicle index page implemented.',
        runId: 'run_01j',
        changedFiles: [
            new ChangedFileData(
                role: ChangedFileRole::Page,
                action: ChangedFileAction::Create,
                path: 'resources/js/pages/vehicles/index.tsx',
            ),
            new ChangedFileData(
                role: ChangedFileRole::Feature,
                action: ChangedFileAction::Modify,
                path: 'resources/js/features/vehicles/vehicle-table.tsx',
            ),
        ],
        auditArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/audit.json',
            schema: 'iak.audit.v1',
        ),
        testsArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/tests.json',
        ),
        verifyArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/verify.json',
            schema: 'iak.verify.v1',
        ),
        feedbackUnresolved: 0,
        notes: ['No blockers remain.'],
        nextActions: [
            new NextActionData(
                type: 'follow_up',
                summary: 'Watch the follow-up deploy.',
                blocking: false,
            ),
        ],
    );

    expect($request->jsonSerialize())->toBe([
        'command' => 'iak:handoff',
        'status' => 'completed',
        'summary' => 'Vehicle index page implemented.',
        'runId' => 'run_01j',
        'task' => 'Create vehicle index page',
        'changedFiles' => [
            'page' => [[
                'path' => 'resources/js/pages/vehicles/index.tsx',
                'action' => 'create',
            ]],
            'feature' => [[
                'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
                'action' => 'modify',
            ]],
        ],
        'audit' => '.iak/runs/run_01j/audit.json',
        'tests' => '.iak/runs/run_01j/tests.json',
        'verify' => '.iak/runs/run_01j/verify.json',
        'feedbackUnresolved' => 0,
        'notes' => ['No blockers remain.'],
        'nextActions' => [[
            'type' => 'follow_up',
            'summary' => 'Watch the follow-up deploy.',
            'blocking' => false,
        ]],
    ]);
});

test('serializes default create handoff input without optional fields', function (): void {
    $request = new CreateHandoffData;

    expect($request->jsonSerialize())->toBe([
        'command' => 'iak:handoff',
        'status' => 'completed',
        'summary' => '',
    ]);
});
