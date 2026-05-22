<?php

declare(strict_types=1);

use InertiaAgentKit\Handoff\HandoffCreator;

it('creates a first port handoff payload from command like input', function (): void {
    $payload = (new HandoffCreator())->create([
        'runId' => 'run_01j',
        'task' => 'Create vehicle index page',
        'summary' => 'Vehicle index page implemented.',
        'status' => 'completed',
        'changedFile' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
            'feature:modify:resources/js/features/vehicles/vehicle-table.tsx',
        ],
        'audit' => '.iak/runs/run_01j/audit.json',
        'tests' => '.iak/runs/run_01j/tests.json',
        'verify' => '.iak/runs/run_01j/verify.json',
        'feedbackUnresolved' => '0',
        'note' => ['No blockers remain.'],
        'nextAction' => ['Watch the follow-up deploy.'],
    ]);

    expect($payload['schema'])->toBe('iak.handoff.v1')
        ->and($payload['version'])->toBe(1)
        ->and($payload['command'])->toBe('iak:handoff')
        ->and($payload['runId'])->toBe('run_01j')
        ->and($payload['status'])->toBe('completed')
        ->and($payload['changedFiles']['page'][0])->toEqual([
            'path' => 'resources/js/pages/vehicles/index.tsx',
            'action' => 'create',
        ])
        ->and($payload['changedFiles']['feature'][0])->toEqual([
            'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
            'action' => 'modify',
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
        ->and($payload['errors'])->toBe([]);
});

it('uses defaults when optional command input is omitted', function (): void {
    $payload = (new HandoffCreator())->create([]);

    expect($payload['runId'])->toStartWith('run_')
        ->and($payload['status'])->toBe('completed')
        ->and($payload['summary'])->toBe('')
        ->and($payload['changedFiles'])->toBeInstanceOf(stdClass::class)
        ->and($payload['evidence']['audit'])->toBe([
            'status' => null,
            'artifact' => null,
        ])
        ->and($payload['evidence']['tests'])->toBe([
            'status' => null,
            'artifact' => null,
        ])
        ->and($payload['evidence']['verify'])->toBe([
            'artifact' => null,
        ])
        ->and($payload['evidence']['feedback']['unresolved'])->toBeNull()
        ->and($payload['artifacts']['handoff']['path'])->toBe('.iak/runs/'.$payload['runId'].'/handoff.json');
});

it('generates the handoff artifact path from the configured runs path', function (): void {
    $payload = (new HandoffCreator())->create([
        'runId' => 'run_custom',
    ], [
        'paths' => [
            'runs' => '.custom/runs/',
        ],
    ]);

    expect($payload['artifacts']['handoff']['path'])->toBe('.custom/runs/run_custom/handoff.json');
});

it('merges grouped changed files with repeated changed file entries', function (): void {
    $payload = (new HandoffCreator())->create([
        'runId' => 'run_merge',
        'changedFiles' => [
            'page' => [[
                'path' => 'resources/js/pages/vehicles/index.tsx',
                'action' => 'create',
            ]],
        ],
        'changedFile' => [
            'feature:create:resources/js/features/vehicles/vehicle-table.tsx',
        ],
    ]);

    expect($payload['changedFiles'])->toEqual([
        'page' => [[
            'path' => 'resources/js/pages/vehicles/index.tsx',
            'action' => 'create',
        ]],
        'feature' => [[
            'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
            'action' => 'create',
        ]],
    ]);
});

it('preserves changed file parse errors and blocks the handoff', function (): void {
    $payload = (new HandoffCreator())->create([
        'runId' => 'run_blocked',
        'status' => 'completed',
        'changedFile' => [
            'feature:modify:resources/js/features/vehicles/vehicle-table.tsx',
            'feature:touch:resources/js/features/vehicles/vehicle-form.tsx',
            'feature:modify:../outside.ts',
        ],
    ]);

    expect($payload['status'])->toBe('blocked')
        ->and($payload['changedFiles'])->toEqual([
            'feature' => [[
                'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
                'action' => 'modify',
            ]],
        ])
        ->and($payload['errors'])->toHaveCount(2)
        ->and(array_column($payload['errors'], 'code'))->toContain('changed_file.invalid_action')
        ->and(array_column($payload['errors'], 'code'))->toContain('changed_file.invalid_path');
});
