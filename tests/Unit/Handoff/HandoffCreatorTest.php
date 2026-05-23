<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use InertiaAgentKit\Handoff\HandoffCreator;
use Symfony\Component\Uid\Ulid;

require_once __DIR__.'/../../Utils/HandoffTestFunctionHooks.php';

test('creates a first port handoff payload from command like input', function (): void {
    $payloadFactory = (static fn (): array => [
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

    $payload = (new HandoffCreator)->create($payloadFactory());

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

test('uses defaults when optional command input is omitted', function (): void {
    $payload = (new HandoffCreator)->create([]);

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

test('generates the handoff artifact path from the configured runs path', function (): void {
    $payload = (new HandoffCreator)->create([
        'runId' => 'run_custom',
    ], [
        'paths' => [
            'runs' => '.custom/runs/',
        ],
    ]);

    expect($payload['artifacts']['handoff']['path'])->toBe('.custom/runs/run_custom/handoff.json');
});

test('falls back to default runs path when configured path normalizes to blank', function (): void {
    $payload = (new HandoffCreator)->create([
        'runId' => 'run_blank_runs_path',
    ], [
        'paths' => [
            'runs' => '/',
        ],
    ]);

    expect($payload['artifacts']['handoff']['path'])->toBe('.iak/runs/run_blank_runs_path/handoff.json');
});

test('falls back to default paths and schema values when config is empty', function (): void {
    $payloadFactory = (static fn (): array => [
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

    $input = $payloadFactory();

    $input['runId'] = 'run_fallbacks';
    $input['audit'] = '.iak/runs/run_fallbacks/audit.json';
    $input['tests'] = '.iak/runs/run_fallbacks/tests.json';
    $input['verify'] = '.iak/runs/run_fallbacks/verify.json';

    $payload = (new HandoffCreator)->create($input, [
        'json_schemas' => [
            'handoff' => '',
            'audit' => '',
            'verify' => '',
        ],
        'paths' => [
            'runs' => '',
        ],
    ]);

    expect($payload['schema'])->toBe('iak.handoff.v1')
        ->and($payload['evidence']['audit']['artifact'])->toMatchArray([
            'kind' => 'json',
            'path' => '.iak/runs/run_fallbacks/audit.json',
            'schema' => 'iak.audit.v1',
        ])
        ->and($payload['evidence']['verify']['artifact']['schema'])->toBe('iak.verify.v1')
        ->and($payload['artifacts']['handoff']['path'])->toBe('.iak/runs/run_fallbacks/handoff.json');
});

test('falls back to random-based run ID when ULID generation fails', function (): void {
    $previousRandomBytesBehavior = getenv('I_AK_FORCE_RANDOM_BYTES_THROW');

    try {
        putenv('I_AK_FORCE_RANDOM_BYTES_THROW=0');
        Str::createUlidsUsing(static function (): Ulid {
            throw new RuntimeException('forced ulid failure');
        });

        $payload = (new HandoffCreator)->create([]);

        expect($payload['runId'])->toMatch('/^run_[0-9a-f]{16}$/');
    } finally {
        Str::createUlidsNormally();
        if ($previousRandomBytesBehavior === false) {
            putenv('I_AK_FORCE_RANDOM_BYTES_THROW');
        } else {
            putenv('I_AK_FORCE_RANDOM_BYTES_THROW='.$previousRandomBytesBehavior);
        }
    }
});

test('falls back to fallback-generated run ID when random bytes fail', function (): void {
    $previousRandomBytesBehavior = getenv('I_AK_FORCE_RANDOM_BYTES_THROW');

    try {
        putenv('I_AK_FORCE_RANDOM_BYTES_THROW=1');
        Str::createUlidsUsing(static function (): Ulid {
            throw new RuntimeException('forced ulid failure');
        });

        $payload = (new HandoffCreator)->create([]);

        expect($payload['runId'])->toStartWith('run_');
    } finally {
        Str::createUlidsNormally();
        if ($previousRandomBytesBehavior === false) {
            putenv('I_AK_FORCE_RANDOM_BYTES_THROW');
        } else {
            putenv('I_AK_FORCE_RANDOM_BYTES_THROW='.$previousRandomBytesBehavior);
        }
    }
});

test('merges grouped changed files with repeated changed file entries', function (): void {
    $payload = (new HandoffCreator)->create([
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

test('skips grouped changed file entries with invalid role, structure, and entry shape', function (): void {
    $payload = (new HandoffCreator)->create([
        'runId' => 'run_invalid_grouped',
        'changedFiles' => [
            123 => 'invalid',
            'page' => [
                'not-a-row',
                [
                    'path' => 'resources/js/pages/vehicles/index.tsx',
                    'action' => 'create',
                ],
            ],
            'feature' => [
                ['path' => 'resources/js/features/vehicles/vehicle-table.tsx', 'action' => 'create'],
            ],
        ],
        'changedFile' => 'feature:modify:resources/js/features/vehicles/vehicle-form.tsx',
    ]);

    expect($payload['changedFiles'])->toEqual([
        'page' => [[
            'path' => 'resources/js/pages/vehicles/index.tsx',
            'action' => 'create',
        ]],
        'feature' => [[
            'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
            'action' => 'create',
        ], [
            'path' => 'resources/js/features/vehicles/vehicle-form.tsx',
            'action' => 'modify',
        ]],
    ]);
});

test('supports scalar next-action inputs and trims summaries', function (): void {
    $payload = (new HandoffCreator)->create([
        'runId' => 'run_scalar_actions',
        'nextActions' => ['  ', 'Fix follow-up bug'],
        'nextAction' => ['   ', '  run checks'],
        'next-action' => ['  another action  '],
    ]);

    expect($payload['nextActions'])->toEqual([
        [
            'type' => 'follow_up',
            'summary' => 'Fix follow-up bug',
            'blocking' => false,
        ],
        [
            'type' => 'follow_up',
            'summary' => 'run checks',
            'blocking' => false,
        ],
        [
            'type' => 'follow_up',
            'summary' => 'another action',
            'blocking' => false,
        ],
    ]);
});

test('ignores non-scalar next and changed file values while building handoff lists', function (): void {
    $payload = (new HandoffCreator)->create([
        'runId' => 'run_scalar_ignored',
        'changedFile' => [
            null,
            true,
            ['page:create:resources/js/pages/vehicles/index.tsx'],
            'page:create:resources/js/pages/vehicles/show.tsx',
        ],
        'nextAction' => [
            true,
            null,
            12,
            '  review changes  ',
        ],
    ]);

    expect($payload['changedFiles']['page'])->toContain([
        'path' => 'resources/js/pages/vehicles/show.tsx',
        'action' => 'create',
    ])
        ->and($payload['nextActions'])->toHaveCount(2)
        ->and($payload['nextActions'])->toContain([
            'type' => 'follow_up',
            'summary' => '12',
            'blocking' => false,
        ])
        ->and($payload['nextActions'])->toContain([
            'type' => 'follow_up',
            'summary' => 'review changes',
            'blocking' => false,
        ]);
});

test('skips grouped changed file rows missing path or action', function (): void {
    $payload = (new HandoffCreator)->create([
        'runId' => 'run_grouped_missing_fields',
        'changedFiles' => [
            'page' => [
                ['path' => 'resources/js/pages/vehicles/index.tsx'],
                ['action' => 'create'],
                ['path' => 'resources/js/pages/vehicles/create.tsx', 'action' => 'create'],
            ],
        ],
    ]);

    expect($payload['changedFiles'])->toEqual([
        'page' => [[
            'path' => 'resources/js/pages/vehicles/create.tsx',
            'action' => 'create',
        ]],
    ]);
});

test('preserves changed file parse errors and blocks the handoff', function (): void {
    $payload = (new HandoffCreator)->create([
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
