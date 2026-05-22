<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-verify-command-'.bin2hex(random_bytes(6));

    mkdir($basePath, 0755, true);

    $this->app->setBasePath($basePath);

    config()->set('inertia-agent-kit.feedback.path', '.iak/feedback');
});

afterEach(function (): void {
    $basePath = base_path();
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-verify-command-';

    if (str_starts_with($basePath, $prefix)) {
        iak_verify_remove_directory($basePath);
    }
});

it('passes with clean audit evidence and no feedback', function (): void {
    iak_verify_clean_fixture(base_path());

    [$exitCode, $payload] = iak_verify_run([
        '--run-id' => 'run_clean',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.verify.v1')
        ->and($payload['event'])->toBe('iak.verify.completed')
        ->and($payload['version'])->toBe(1)
        ->and($payload['command'])->toBe('iak:verify')
        ->and($payload['runId'])->toBe('run_clean')
        ->and($payload['status'])->toBe('passed')
        ->and($payload['mode'])->toBe('first-port')
        ->and($payload['checks'][0]['id'])->toBe('audit')
        ->and($payload['checks'][0]['status'])->toBe('passed')
        ->and($payload['checks'][0]['artifact']['path'])->toBe('.iak/runs/run_clean/audit.json')
        ->and($payload['checks'][0]['artifact']['schema'])->toBe('iak.audit.v1')
        ->and($payload['checks'][1]['id'])->toBe('feedback')
        ->and($payload['checks'][1]['status'])->toBe('passed')
        ->and($payload['checks'][1]['related'])->toBe(0)
        ->and($payload['checks'][1]['unresolved'])->toBe(0)
        ->and($payload['checks'][1]['invalidResolved'])->toBe(0)
        ->and($payload['checks'][2]['id'])->toBe('browser')
        ->and($payload['checks'][2]['status'])->toBe('skipped')
        ->and($payload['checks'][2]['reason'])->toBe('first_port_no_browser_execution')
        ->and($payload['checks'][3]['id'])->toBe('storybook')
        ->and($payload['checks'][3]['status'])->toBe('skipped')
        ->and($payload['checks'][3]['reason'])->toBe('first_port_no_storybook_execution')
        ->and($payload['evidence']['audit']['status'])->toBe('passed')
        ->and($payload['evidence']['audit']['runId'])->toBe('run_clean')
        ->and($payload['evidence']['audit']['violations'])->toBe(0)
        ->and($payload['evidence']['feedback']['related'])->toBe(0)
        ->and($payload['evidence']['feedback']['unresolved'])->toBe(0)
        ->and($payload['evidence']['screenshots']['status'])->toBe('placeholder')
        ->and($payload['evidence']['screenshots']['artifact']['path'])->toBe('.iak/runs/run_clean/screenshots/metadata.json')
        ->and($payload['errors'])->toBe([])
        ->and(iak_verify_read_json('.iak/runs/run_clean/verify.json'))->toEqual($payload)
        ->and(iak_verify_read_json('.iak/runs/run_clean/screenshots/metadata.json'))->toMatchArray([
            'schema' => 'iak.verify.screenshots.v1',
            'runId' => 'run_clean',
            'status' => 'placeholder',
        ]);
});

it('fails when the audit reports a violation', function (): void {
    iak_verify_clean_fixture(base_path());
    iak_verify_write('resources/js/features/vehicles/vehicle-table.tsx', <<<'TSX'
export function VehicleTable() {
    return <section className="p-[34px] bg-blue-500 text-ds-body">Vehicles</section>;
}
TSX);

    [$exitCode, $payload] = iak_verify_run([
        '--run-id' => 'run_audit_failed',
    ]);

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('failed')
        ->and($payload['evidence']['audit']['status'])->toBe('failed')
        ->and($payload['evidence']['audit']['artifact']['path'])->toBe('.iak/runs/run_audit_failed/audit.json')
        ->and($payload['checks'][0]['totals']['errors'])->toBeGreaterThan(0)
        ->and(iak_verify_read_json('.iak/runs/run_audit_failed/audit.json')['status'])->toBe('failed');
});

it('fails when related feedback is pending', function (): void {
    iak_verify_clean_fixture(base_path());
    iak_verify_write_feedback([
        'id' => 'fbk_pending',
        'status' => 'pending',
    ]);

    [$exitCode, $payload] = iak_verify_run([
        '--run-id' => 'run_pending_feedback',
    ]);

    $unresolved = iak_verify_read_json('.iak/runs/run_pending_feedback/feedback/unresolved.json');

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('failed')
        ->and($payload['evidence']['feedback'])->toMatchArray([
            'related' => 1,
            'unresolved' => 1,
            'invalidResolved' => 0,
            'ids' => ['fbk_pending'],
        ])
        ->and($payload['errors'][0]['code'])->toBe('feedback.unresolved')
        ->and($unresolved['count'])->toBe(1)
        ->and($unresolved['items'][0]['id'])->toBe('fbk_pending')
        ->and($unresolved['items'][0]['status'])->toBe('pending');
});

it('passes while preparing evidence for the target feedback id', function (): void {
    iak_verify_clean_fixture(base_path());
    iak_verify_write_feedback([
        'id' => 'fbk_target',
        'status' => 'in_progress',
    ]);

    [$exitCode, $payload] = iak_verify_run([
        '--run-id' => 'run_feedback_target',
        '--feedback' => 'fbk_target',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('passed')
        ->and($payload['scope']['feedback'])->toBe(['fbk_target'])
        ->and($payload['evidence']['feedback'])->toMatchArray([
            'related' => 1,
            'unresolved' => 0,
            'target' => 'fbk_target',
            'ids' => [],
            'excludedIds' => ['fbk_target'],
        ])
        ->and($payload['commandsRun'][0]['cmd'])->toContain('--feedback=fbk_target');
});

it('blocks for a stale supplied audit artifact', function (): void {
    iak_verify_clean_fixture(base_path());
    $audit = iak_verify_audit_artifact([
        'runId' => 'run_old_audit',
        'artifacts' => [
            'audit' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_old_audit/audit.json',
                'schema' => 'iak.audit.v1',
            ],
        ],
        'meta' => [
            'configHash' => 'sha256:stale',
        ],
    ]);

    iak_verify_write_json('.iak/runs/run_old_audit/audit.json', $audit);

    [$exitCode, $payload] = iak_verify_run([
        '--run-id' => 'run_verify_stale',
        '--audit' => '.iak/runs/run_old_audit/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.stale_artifact')
        ->and($payload['artifacts']['audit']['path'])->toBe('.iak/runs/run_old_audit/audit.json')
        ->and(iak_verify_read_json('.iak/runs/run_verify_stale/verify.json')['status'])->toBe('blocked');
});

it('writes the verify artifact and feedback artifacts for failed feedback runs', function (): void {
    iak_verify_clean_fixture(base_path());
    iak_verify_write_feedback([
        'id' => 'fbk_write_artifacts',
        'status' => 'pending',
    ]);

    [$exitCode, $payload] = iak_verify_run([
        '--run-id' => 'run_write_artifacts',
    ]);

    expect($exitCode)->toBe(1)
        ->and(iak_verify_read_json('.iak/runs/run_write_artifacts/verify.json'))->toEqual($payload)
        ->and(iak_verify_read_json('.iak/runs/run_write_artifacts/feedback/related.json'))->toMatchArray([
            'count' => 1,
        ])
        ->and(iak_verify_read_json('.iak/runs/run_write_artifacts/feedback/unresolved.json'))->toMatchArray([
            'count' => 1,
        ]);
});

/**
 * @param array<string, mixed> $arguments
 *
 * @return array{0: int, 1: array<string, mixed>}
 */
function iak_verify_run(array $arguments = []): array
{
    $exitCode = Artisan::call('iak:verify', [
        ...$arguments,
        '--json' => true,
    ]);

    return [
        $exitCode,
        json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR),
    ];
}

function iak_verify_clean_fixture(string $base): void
{
    iak_verify_write('resources/js/components/ui/button.tsx', <<<'TSX'
export function Button() {
    return <button className="bg-ds-surface text-ds-body border-ds-border">Save</button>;
}
TSX);

    iak_verify_write('resources/js/components/ui/button.stories.tsx', <<<'TSX'
import { Button } from './button';

export default { component: Button };
export const Default = {};
TSX);

    iak_verify_write('resources/js/components/app/filter-bar.tsx', <<<'TSX'
export function FilterBar() {
    return <div className="bg-ds-panel text-ds-muted border-ds-border">Filters</div>;
}
TSX);

    iak_verify_write('resources/js/components/app/filter-bar.stories.tsx', <<<'TSX'
import { FilterBar } from './filter-bar';

export default { component: FilterBar };
export const Default = {};
TSX);

    iak_verify_write('resources/js/features/vehicles/vehicle-table.tsx', <<<'TSX'
import type { VehicleResource } from './vehicle.types';

export function VehicleTable({ vehicles }: { vehicles: VehicleResource[] }) {
    return <section className="bg-ds-surface text-ds-body border-ds-border">{vehicles.length}</section>;
}
TSX);

    iak_verify_write('resources/js/features/vehicles/vehicle-table.stories.tsx', <<<'TSX'
import { VehicleTable } from './vehicle-table';

export default { component: VehicleTable };
export const Default = { args: { vehicles: [] } };
TSX);

    iak_verify_write('resources/js/features/vehicles/vehicle-form.tsx', <<<'TSX'
export function VehicleForm() {
    return <form className="bg-ds-surface text-ds-body border-ds-border" />;
}
TSX);

    iak_verify_write('resources/js/features/vehicles/vehicle-form.stories.tsx', <<<'TSX'
import { VehicleForm } from './vehicle-form';

export default { component: VehicleForm };
export const Default = {};
TSX);

    iak_verify_write('resources/js/features/vehicles/vehicle.types.ts', <<<'TS'
import type { App } from '@/types/generated';

export type VehicleResource = App.Data.VehicleData;
TS);

    iak_verify_write('resources/js/types/generated/index.d.ts', <<<'TS'
export namespace App {
    export namespace Data {
        export type VehicleData = { id: number; name: string };
    }
}
TS);

    iak_verify_write('resources/css/iak/tokens.css', <<<'CSS'
:root {
    --ds-color-surface: #ffffff;
}
CSS);
}

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function iak_verify_write_feedback(array $overrides = []): array
{
    $id = (string) ($overrides['id'] ?? 'fbk_default');
    $record = array_replace_recursive([
        'schema' => 'iak.feedback.v1',
        'id' => $id,
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'human',
        'producer' => 'iak.test',
        'target' => [
            'url' => 'http://localhost/vehicles',
            'route' => 'vehicles.index',
            'storyId' => null,
            'selector' => "[data-iak-part='filter-bar']",
        ],
        'viewport' => [
            'width' => 1440,
            'height' => 900,
            'name' => 'desktop',
        ],
        'message' => 'This should reuse the standard filter bar pattern.',
        'attachments' => [],
        'context' => [
            'componentCandidates' => ['FilterBar'],
        ],
        'resolution' => null,
        'createdAt' => '2026-05-22T15:00:00Z',
        'updatedAt' => '2026-05-22T15:00:00Z',
    ], $overrides);

    iak_verify_write_json(".iak/feedback/{$id}/record.json", $record);

    return $record;
}

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function iak_verify_audit_artifact(array $overrides = []): array
{
    return array_replace_recursive([
        'schema' => 'iak.audit.v1',
        'event' => 'iak.audit.completed',
        'version' => 1,
        'command' => 'iak:audit',
        'runId' => 'run_audit',
        'status' => 'passed',
        'summary' => 'Audit passed.',
        'totals' => [
            'checks' => 0,
            'passed' => 0,
            'failed' => 0,
            'blocked' => 0,
            'findings' => 0,
            'errors' => 0,
            'warnings' => 0,
        ],
        'checks' => [],
        'violations' => [],
        'artifacts' => [
            'audit' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_audit/audit.json',
                'schema' => 'iak.audit.v1',
            ],
        ],
        'nextActions' => [],
        'errors' => [],
        'meta' => [
            'configHash' => iak_verify_config_hash(),
        ],
    ], $overrides);
}

function iak_verify_config_hash(): string
{
    return 'sha256:'.hash('sha256', json_encode(config('inertia-agent-kit'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/**
 * @param array<string, mixed> $payload
 */
function iak_verify_write_json(string $relativePath, array $payload): void
{
    iak_verify_write(
        $relativePath,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
    );
}

function iak_verify_write(string $relativePath, string $contents): void
{
    $path = base_path($relativePath);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, $contents);
}

/**
 * @return array<string, mixed>
 */
function iak_verify_read_json(string $relativePath): array
{
    return json_decode((string) file_get_contents(base_path($relativePath)), true, 512, JSON_THROW_ON_ERROR);
}

function iak_verify_remove_directory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());

            continue;
        }

        unlink($file->getPathname());
    }

    rmdir($path);
}
