<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Tests\Utils\HandoffCommandTestHelper;

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-handoff-command-'.bin2hex(random_bytes(6));
    mkdir($basePath, 0755, true);

    $this->app->setBasePath($basePath);
    $this->helper = new HandoffCommandTestHelper($basePath);
    config()->set('inertia-agent-kit.paths.runs', '.iak/runs');
});

afterEach(function (): void {
    putenv('IAK_AGENT');
    unset($_ENV['IAK_AGENT'], $_SERVER['IAK_AGENT']);

    $basePath = $this->helper->basePath();
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-handoff-command-';

    if (str_starts_with((string) $basePath, $prefix)) {
        $this->helper->removeDirectory();
    }
});

test('creates a JSON handoff and writes the matching artifact', function (): void {
    [$exitCode, $payload] = $this->helper->runJson([
        'action' => 'create',
        '--json' => true,
        '--run-id' => 'run_create',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
            'test:create:tests/Feature/VehicleIndexTest.php',
        ],
        '--audit' => '.iak/runs/run_create/audit.json',
        '--tests' => '.iak/runs/run_create/tests.json',
        '--feedback-unresolved' => '0',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.handoff.v1')
        ->and($payload['version'])->toBe(1)
        ->and($payload['command'])->toBe('iak:handoff')
        ->and($payload['action'])->toBe('create')
        ->and($payload['runId'])->toBe('run_create')
        ->and($payload['status'])->toBe('completed')
        ->and($payload['summary'])->toBe('Vehicle index handoff is ready.')
        ->and($payload['changedFiles'])->toMatchArray([
            'page' => [
                [
                    'path' => 'resources/js/pages/vehicles/index.tsx',
                    'action' => 'create',
                ],
            ],
            'test' => [
                [
                    'path' => 'tests/Feature/VehicleIndexTest.php',
                    'action' => 'create',
                ],
            ],
        ])
        ->and($payload['evidence'])->toMatchArray([
            'audit' => [
                'status' => 'pending',
                'artifact' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run_create/audit.json',
                    'schema' => 'iak.audit.v1',
                ],
            ],
            'tests' => [
                'status' => 'pending',
                'artifact' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run_create/tests.json',
                ],
            ],
            'feedback' => [
                'unresolved' => 0,
            ],
            'verify' => [
                'artifact' => null,
            ],
        ])
        ->and($payload['artifacts']['handoff']['path'])->toBe('.iak/runs/run_create/handoff.json')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written')
        ->and($payload['artifacts']['handoff']['kind'])->toBe('json')
        ->and($payload['artifacts']['handoff']['schema'])->toBe('iak.handoff.v1')
        ->and($payload['notes'])->toBe([])
        ->and($payload['nextActions'])->toBe([])
        ->and($payload['errors'])->toBe([])
        ->and($payload['meta']['createdAt'])->toBeString()
        ->and($payload['meta']['package'])->toBe('fbarrento/inertia-agent-kit')
        ->and($payload['meta']['iakVersion'])->toBe('0.1.0')
        ->and($this->helper->readJson('.iak/runs/run_create/handoff.json'))->toEqual($payload);
});

test('validates a handoff with existing audit tests and handoff artifacts', function (): void {
    $payload = $this->helper->seedValidPayload('run_valid');

    [$exitCode, $result] = $this->helper->runJson([
        'action' => 'validate',
        'path' => '.iak/runs/run_valid/handoff.json',
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and($result['schema'])->toBe('iak.handoff.v1')
        ->and($result['command'])->toBe('iak:handoff')
        ->and($result['action'])->toBe('validate')
        ->and($result['status'])->toBe('valid')
        ->and($result['valid'])->toBeTrue()
        ->and($result['path'])->toBe('.iak/runs/run_valid/handoff.json')
        ->and($result['runId'])->toBe($payload['runId'])
        ->and($result['errors'])->toBe([])
        ->and($result['nextActions'])->toBe([])
        ->and($result['meta']['source'])->toBe('validator');
});

test('returns structured validation errors for invalid changed files and missing artifacts', function (): void {
    $payload = $this->helper->seedValidPayload('run_valid');
    $payload['changedFiles']['page'][0]['path'] = '../outside.php';

    $this->helper->writeJson('.iak/runs/run_valid/audit.json', ['schema' => 'iak.audit.v1']);
    $this->helper->writeJson('.iak/runs/run_valid/handoff.json', $payload);

    [$exitCode, $result] = $this->helper->runJson([
        'action' => 'validate',
        'path' => '.iak/runs/run_valid/handoff.json',
        '--json' => true,
    ]);

    $codes = array_column($result['errors'], 'code');

    expect($exitCode)->toBe(2)
        ->and($result['valid'])->toBeFalse()
        ->and($result['status'])->toBe('invalid')
        ->and($codes)->toContain('handoff.changed_files.path_invalid')
        ->and($result['path'])->toBe('.iak/runs/run_valid/handoff.json');
});

test('returns JSON errors for invalid create changed-file input', function (): void {
    [$exitCode, $payload] = $this->helper->runJson([
        'action' => 'create',
        '--json' => true,
        '--run-id' => 'run_bad_changed_file',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--changed-file' => [
            'feature:touch:resources/js/features/vehicles/table.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written')
        ->and(array_column($payload['errors'], 'code'))->toContain('changed_file.invalid_action');
});

test('emits JSON when IAK_AGENT is set without the json option', function (): void {
    putenv('IAK_AGENT=1');
    $_ENV['IAK_AGENT'] = '1';
    $_SERVER['IAK_AGENT'] = '1';

    $exitCode = Artisan::call('iak:handoff', [
        'action' => 'create',
        '--run-id' => 'run_agent',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    $output = Artisan::output();
    $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.handoff.v1')
        ->and($payload['runId'])->toBe('run_agent')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written');
});

test('returns an invalid payload for unsupported actions through command delegation', function (): void {
    [$exitCode, $payload] = $this->helper->runJson([
        'action' => 'noop',
        '--json' => true,
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['action'])->toBe('noop')
        ->and($payload['errors'][0]['code'])->toBe('handoff.action.invalid');
});

test('defaults an explicitly blank action argument to create', function (): void {
    [$exitCode, $payload] = $this->helper->runJson([
        'action' => '',
        '--json' => true,
        '--run-id' => 'run_blank_action',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['action'])->toBe('create');
});

test('filters non-scalar and empty notes and next-actions from command input', function (): void {
    [$exitCode, $payload] = $this->helper->runJson([
        'action' => 'create',
        '--json' => true,
        '--run-id' => 'run_notes_next_action',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--note' => [
            'first note',
            '',
            [],
            '',
            '  ',
        ],
        '--next-action' => [
            'first next action',
            [],
            '',
            [],
        ],
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['notes'])->toBe(['first note'])
        ->and($payload['nextActions'])->toMatchArray([
            [
                'type' => 'follow_up',
                'summary' => 'first next action',
                'blocking' => false,
            ],
        ]);
});

test('returns a command-level failure when JSON encoding fails', function (): void {
    $invalidSummary = "\xC3\x28".' invalid utf8';

    [$exitCode, $output] = $this->helper->run([
        'action' => 'create',
        '--json' => true,
        '--run-id' => 'run_invalid_json',
        '--task' => 'Create vehicle index page',
        '--summary' => $invalidSummary,
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    expect($exitCode)->toBe(4)
        ->and(trim((string) $output))->toBe('');
});

test('returns summary output when JSON mode is disabled', function (): void {
    [$exitCode, $output] = $this->helper->run([
        'action' => 'create',
        '--run-id' => 'run_no_json',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    expect($exitCode)->toBe(0)
        ->and(trim((string) $output))->toBe('Vehicle index handoff is ready.');
});

test('defaults to create action when no action argument is provided', function (): void {
    [$exitCode, $payload] = $this->helper->runJson([
        '--json' => true,
        '--run-id' => 'run_default_action',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['action'])->toBe('create')
        ->and($payload['runId'])->toBe('run_default_action');
});

test('emits pretty JSON when pretty flag is requested', function (): void {
    [$exitCode, $output] = $this->helper->run([
        'action' => 'create',
        '--run-id' => 'run_pretty',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
        '--json' => true,
        '--pretty' => true,
    ]);

    $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(is_string($output))->toBeTrue()
        ->and(str_starts_with(trim((string) $output), '{'))
        ->and(str_contains(trim((string) $output), '  "schema"'))
        ->and(is_array($decoded))->toBeTrue()
        ->and($decoded['runId'])->toBe('run_pretty');
});

test('falls back to a generic summary when no summary is available', function (): void {
    [$exitCode, $output] = $this->helper->run([
        'action' => 'create',
        '--run-id' => 'run_summary_fallback',
        '--task' => 'Create vehicle index page',
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    expect($exitCode)->toBe(0)
        ->and(trim((string) $output))->toBe('Handoff command finished.');
});
