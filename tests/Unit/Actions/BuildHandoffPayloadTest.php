<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\BuildHandoffPayload;
use InertiaAgentKit\Data\ArtifactReferenceData;
use InertiaAgentKit\Data\ChangedFileData;
use InertiaAgentKit\Data\CreateHandoffData;
use InertiaAgentKit\Data\NextActionData;
use InertiaAgentKit\Enum\ArtifactKind;
use InertiaAgentKit\Enum\ChangedFileAction;
use InertiaAgentKit\Enum\ChangedFileRole;
use InertiaAgentKit\Enum\HandoffStatus;

beforeEach(function (): void {
    $this->buildHandoffPayload = new BuildHandoffPayload;
});

test('builds the stable handoff payload from create handoff data', function (): void {
    $payload = $this->buildHandoffPayload->handle(new CreateHandoffData(
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
        ],
        auditArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/audit.json',
        ),
        testsArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/tests.json',
        ),
        verifyArtifact: new ArtifactReferenceData(
            kind: ArtifactKind::Json,
            path: '.iak/runs/run_01j/verify.json',
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
    ));

    expect($payload['schema'])->toBe('iak.handoff.v1')
        ->and($payload['version'])->toBe(1)
        ->and($payload['command'])->toBe('iak:handoff')
        ->and($payload['runId'])->toBe('run_01j')
        ->and($payload['status'])->toBe('completed')
        ->and($payload['changedFiles'])->toEqual([
            'page' => [[
                'path' => 'resources/js/pages/vehicles/index.tsx',
                'action' => 'create',
            ]],
        ])
        ->and($payload['evidence']['audit']['status'])->toBe('pending')
        ->and($payload['evidence']['audit']['artifact'])->toMatchArray([
            'kind' => 'json',
            'path' => '.iak/runs/run_01j/audit.json',
            'schema' => 'iak.audit.v1',
        ])
        ->and($payload['evidence']['tests']['status'])->toBe('pending')
        ->and($payload['evidence']['tests']['artifact'])->toMatchArray([
            'kind' => 'json',
            'path' => '.iak/runs/run_01j/tests.json',
        ])
        ->and($payload['evidence']['verify']['artifact'])->toMatchArray([
            'kind' => 'json',
            'path' => '.iak/runs/run_01j/verify.json',
            'schema' => 'iak.verify.v1',
        ])
        ->and($payload['evidence']['feedback']['unresolved'])->toBe(0)
        ->and($payload['artifacts']['handoff'])->toMatchArray([
            'kind' => 'json',
            'path' => '.iak/runs/run_01j/handoff.json',
            'schema' => 'iak.handoff.v1',
            'status' => 'not_written',
        ])
        ->and($payload['notes'])->toBe(['No blockers remain.'])
        ->and($payload['nextActions'][0])->toMatchArray([
            'type' => 'follow_up',
            'summary' => 'Watch the follow-up deploy.',
            'blocking' => false,
        ])
        ->and($payload['errors'])->toBe([])
        ->and($payload['meta']['requested'])->toBe([
            'status' => 'completed',
            'changedFile' => [],
            'hasGroupedChangedFiles' => true,
        ]);
});

test('accepts create handoff arrays for thin command orchestration', function (): void {
    $payload = $this->buildHandoffPayload->handle([
        'runId' => 'run_array_input',
        'task' => 'Create handoff from CLI array',
        'status' => 'completed',
        'summary' => 'Array input is supported by action lane.',
        'changedFile' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
    ]);

    expect($payload['runId'])->toBe('run_array_input')
        ->and($payload['status'])->toBe('completed')
        ->and($payload['changedFiles'])->toBe([
            'page' => [[
                'path' => 'resources/js/pages/vehicles/index.tsx',
                'action' => 'create',
            ]],
        ]);
});

test('passes config through to the handoff builder', function (): void {
    $payload = $this->buildHandoffPayload->handle(new CreateHandoffData(
        runId: 'run_custom',
    ), [
        'paths' => [
            'runs' => '.custom/runs',
        ],
    ]);

    expect($payload['artifacts']['handoff']['path'])->toBe('.custom/runs/run_custom/handoff.json');
});
