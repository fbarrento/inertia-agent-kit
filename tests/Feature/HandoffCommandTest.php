<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-handoff-command-'.bin2hex(random_bytes(6));

    mkdir($basePath, 0755, true);

    $this->app->setBasePath($basePath);

    config()->set('inertia-agent-kit.paths.runs', '.iak/runs');
});

afterEach(function (): void {
    putenv('IAK_AGENT');
    unset($_ENV['IAK_AGENT'], $_SERVER['IAK_AGENT']);

    $basePath = base_path();
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-handoff-command-';

    if (str_starts_with($basePath, $prefix)) {
        iak_handoff_command_remove_directory($basePath);
    }
});

it('creates a JSON handoff and writes the matching artifact', function (): void {
    [$exitCode, $payload] = iak_handoff_command_run([
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
        ->and($payload['runId'])->toBe('run_create')
        ->and($payload['status'])->toBe('completed')
        ->and($payload['artifacts']['handoff']['path'])->toBe('.iak/runs/run_create/handoff.json')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written')
        ->and(iak_handoff_command_read_json('.iak/runs/run_create/handoff.json'))->toEqual($payload);
});

it('validates a handoff with existing audit tests and handoff artifacts', function (): void {
    $payload = iak_handoff_command_seed_handoff();

    [$exitCode, $result] = iak_handoff_command_run([
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

it('returns structured validation errors for invalid changed files and missing artifacts', function (): void {
    $payload = iak_handoff_command_valid_payload();
    $payload['changedFiles']['page'][0]['path'] = '../outside.php';

    iak_handoff_command_write_json('.iak/runs/run_valid/audit.json', ['schema' => 'iak.audit.v1']);
    iak_handoff_command_write_json('.iak/runs/run_valid/handoff.json', $payload);

    [$exitCode, $result] = iak_handoff_command_run([
        'action' => 'validate',
        'path' => '.iak/runs/run_valid/handoff.json',
        '--json' => true,
    ]);

    $codes = array_column($result['errors'], 'code');

    expect($exitCode)->toBe(2)
        ->and($result['valid'])->toBeFalse()
        ->and($result['status'])->toBe('invalid')
        ->and($codes)->toContain('handoff.changed_files.path_invalid')
        ->and($codes)->toContain('handoff.artifact.missing')
        ->and($result['path'])->toBe('.iak/runs/run_valid/handoff.json');
});

it('returns JSON errors for invalid create changed-file input', function (): void {
    [$exitCode, $payload] = iak_handoff_command_run([
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

it('emits JSON when IAK_AGENT is set without the json option', function (): void {
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

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.handoff.v1')
        ->and($payload['runId'])->toBe('run_agent')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written');
});

/**
 * @param array<string, mixed> $arguments
 *
 * @return array{0: int, 1: array<string, mixed>}
 */
function iak_handoff_command_run(array $arguments): array
{
    $exitCode = Artisan::call('iak:handoff', $arguments);

    return [
        $exitCode,
        json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR),
    ];
}

/**
 * @return array<string, mixed>
 */
function iak_handoff_command_seed_handoff(): array
{
    $payload = iak_handoff_command_valid_payload();

    iak_handoff_command_write_json('.iak/runs/run_valid/audit.json', ['schema' => 'iak.audit.v1']);
    iak_handoff_command_write_json('.iak/runs/run_valid/tests.json', ['schema' => 'iak.tests.v1']);
    iak_handoff_command_write_json('.iak/runs/run_valid/handoff.json', $payload);

    return $payload;
}

/**
 * @return array<string, mixed>
 */
function iak_handoff_command_valid_payload(): array
{
    return [
        'schema' => 'iak.handoff.v1',
        'runId' => 'run_valid',
        'task' => 'Create vehicle index page',
        'status' => 'completed',
        'summary' => 'Vehicle index page implemented and verified.',
        'changedFiles' => [
            'page' => [[
                'path' => 'resources/js/pages/vehicles/index.tsx',
                'action' => 'create',
            ]],
            'test' => [[
                'path' => 'tests/Feature/VehicleIndexTest.php',
                'action' => 'create',
            ]],
        ],
        'evidence' => [
            'audit' => [
                'status' => 'passed',
                'artifact' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run_valid/audit.json',
                ],
            ],
            'tests' => [
                'status' => 'passed',
                'artifact' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run_valid/tests.json',
                ],
            ],
            'feedback' => [
                'unresolved' => 0,
            ],
        ],
        'artifacts' => [
            'handoff' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_valid/handoff.json',
                'status' => 'written',
            ],
        ],
        'notes' => [],
        'nextActions' => [],
        'errors' => [],
    ];
}

/**
 * @param array<string, mixed> $value
 */
function iak_handoff_command_write_json(string $path, array $value): void
{
    iak_handoff_command_write(
        $path,
        json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
    );
}

/**
 * @return array<string, mixed>
 */
function iak_handoff_command_read_json(string $path): array
{
    $contents = file_get_contents(base_path($path));

    if ($contents === false) {
        throw new RuntimeException("Unable to read JSON fixture [{$path}].");
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException("JSON fixture [{$path}] must contain an object.");
    }

    return $decoded;
}

function iak_handoff_command_write(string $path, string $contents): void
{
    $absolutePath = base_path($path);
    $directory = dirname($absolutePath);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($absolutePath, $contents);
}

function iak_handoff_command_remove_directory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}
