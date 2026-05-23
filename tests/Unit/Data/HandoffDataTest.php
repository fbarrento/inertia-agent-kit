<?php

declare(strict_types=1);

use InertiaAgentKit\Data\ArtifactReferenceData;
use InertiaAgentKit\Data\ChangedFileData;
use InertiaAgentKit\Data\HandoffData;
use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Data\HandoffEvidenceData;
use InertiaAgentKit\Data\HandoffMetaData;
use InertiaAgentKit\Data\NextActionData;
use InertiaAgentKit\Enum\ArtifactKind;
use InertiaAgentKit\Enum\ChangedFileAction;
use InertiaAgentKit\Enum\ChangedFileRole;
use InertiaAgentKit\Enum\EvidenceStatus;
use InertiaAgentKit\Enum\HandoffStatus;

test('serializes handoff data with default collections as empty objects and lists', function (): void {
    $handoff = new HandoffData(
        runId: 'run-empty',
        task: null,
        status: HandoffStatus::Completed,
        summary: 'No changed files.',
        evidence: new HandoffEvidenceData,
        handoffArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run-empty/handoff.json',
            schema: 'iak.handoff.v1',
        ),
    );

    expect($handoff->jsonSerialize()['changedFiles'])->toBeInstanceOf(stdClass::class)
        ->and($handoff->jsonSerialize()['nextActions'])->toBe([])
        ->and($handoff->jsonSerialize()['errors'])->toBe([])
        ->and($handoff->jsonSerialize()['meta'] ?? null)->toBeNull();
});

test('serializes grouped changed files, next actions, and handoff errors', function (): void {
    $handoff = new HandoffData(
        runId: 'run-filled',
        task: 'Build screen',
        status: HandoffStatus::Blocked,
        summary: 'Need follow-up.',
        evidence: new HandoffEvidenceData(
            auditArtifact: new ArtifactReferenceData(
                kind: ArtifactKind::Json,
                path: '.iak/runs/run-filled/audit.json',
                schema: 'iak.audit.v1',
                status: 'not_written',
            ),
            auditStatus: EvidenceStatus::Passed,
            testsArtifact: new ArtifactReferenceData(
                kind: ArtifactKind::Screenshot,
                path: '.iak/runs/run-filled/tests.png',
                schema: 'iak.evidence.v1',
            ),
            testsStatus: EvidenceStatus::Failed,
            verifyArtifact: new ArtifactReferenceData(
                kind: ArtifactKind::Json,
                path: '.iak/runs/run-filled/verify.json',
                schema: 'iak.verify.v1',
                status: 'pending',
            ),
            feedbackUnresolved: 1,
        ),
        handoffArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run-filled/handoff.json',
        ),
        changedFiles: [
            new ChangedFileData(ChangedFileRole::Page, ChangedFileAction::Create, 'resources/js/pages/index.tsx'),
            new ChangedFileData(ChangedFileRole::ComponentUi, ChangedFileAction::Modify, 'resources/js/components/button.ts'),
            new ChangedFileData(ChangedFileRole::Page, ChangedFileAction::Modify, 'resources/js/pages/about.tsx'),
        ],
        notes: ['Note A', 'Note B'],
        nextActions: [
            new NextActionData('run', 'Execute follow-up command', command: 'composer test'),
            new NextActionData('review', 'Inspect failing tests', blocking: true),
        ],
        errors: [
            new HandoffErrorData(
                code: 'handoff.required',
                message: 'A required file was not changed.',
                file: 'resources/js/pages/index.tsx',
                line: 42,
                details: ['fileType' => 'page'],
            ),
        ],
        meta: new HandoffMetaData(
            createdAt: '2026-05-23T00:00:00Z',
            package: 'fbarrento/inertia-agent-kit',
            iakVersion: '0.1.0',
            source: 'tests',
            requestedStatus: 'blocked',
            requestedChangedFiles: ['resources/js/pages/index.tsx'],
            hasGroupedChangedFiles: true,
        ),
    );

    $payload = $handoff->jsonSerialize();

    expect($payload['status'])->toBe('blocked')
        ->and($payload['changedFiles']['page'])->toBe([
            ['path' => 'resources/js/pages/index.tsx', 'action' => 'create'],
            ['path' => 'resources/js/pages/about.tsx', 'action' => 'modify'],
        ])
        ->and($payload['changedFiles']['component-ui'][0])->toMatchArray([
            'path' => 'resources/js/components/button.ts',
            'action' => 'modify',
        ])
        ->and($payload['nextActions'][0])->toBe([
            'type' => 'run',
            'summary' => 'Execute follow-up command',
            'command' => 'composer test',
        ])
        ->and($payload['errors'][0])->toMatchArray([
            'code' => 'handoff.required',
            'message' => 'A required file was not changed.',
            'file' => 'resources/js/pages/index.tsx',
            'line' => 42,
            'details' => ['fileType' => 'page'],
        ])
        ->and($payload['notes'])->toBe([
            'Note A',
            'Note B',
        ])
        ->and($payload['meta'])->toBe([
            'createdAt' => '2026-05-23T00:00:00Z',
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => '0.1.0',
            'source' => 'tests',
            'requested' => [
                'status' => 'blocked',
                'changedFile' => ['resources/js/pages/index.tsx'],
                'hasGroupedChangedFiles' => true,
            ],
        ])
        ->and($payload['evidence'])->toBe([
            'audit' => [
                'status' => 'passed',
                'artifact' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run-filled/audit.json',
                    'schema' => 'iak.audit.v1',
                    'status' => 'not_written',
                ],
            ],
            'tests' => [
                'status' => 'failed',
                'artifact' => [
                    'kind' => 'screenshot',
                    'path' => '.iak/runs/run-filled/tests.png',
                    'schema' => 'iak.evidence.v1',
                ],
            ],
            'verify' => [
                'artifact' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run-filled/verify.json',
                    'schema' => 'iak.verify.v1',
                    'status' => 'pending',
                ],
            ],
            'feedback' => [
                'unresolved' => 1,
            ],
        ]);
});
