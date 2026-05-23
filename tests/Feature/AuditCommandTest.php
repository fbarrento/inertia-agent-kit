<?php

declare(strict_types=1);

use Tests\Utils\AuditCommandTestHelper;
use Tests\Utils\AuditTestHelper;

beforeEach(function (): void {
    $this->base = AuditCommandTestHelper::useTempBase($this->app);
});

afterEach(function (): void {
    $directory = $this->base ?? null;

    if (! is_string($directory) || ! is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
});

test('passes a clean scaffold-like fixture', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_clean');

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.audit.v1')
        ->and($payload['event'])->toBe('iak.audit.completed')
        ->and($payload['status'])->toBe('passed')
        ->and($payload['totals']['errors'])->toBe(0)
        ->and($payload['violations'])->toBe([])
        ->and($payload['artifacts']['audit']['path'])->toBe('.iak/runs/run_clean/audit.json');
});

test('reports design-system violations with locations and fingerprints', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);
    AuditCommandTestHelper::write($base, 'resources/js/features/vehicles/vehicle-table.tsx', <<<'TSX'
export function VehicleTable() {
    const swatch = '#ffffff';

    return <section className="p-[34px] bg-blue-500 text-ds-body">{swatch}</section>;
}
TSX);

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_design_violations');
    $rules = array_column($payload['violations'], 'rule');

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('failed')
        ->and($rules)->toContain('iak/design-system/no-arbitrary-value')
        ->and($rules)->toContain('iak/design-system/no-raw-hex')
        ->and($rules)->toContain('iak/design-system/no-primitive-color');

    foreach ([
        'iak/design-system/no-arbitrary-value' => 'p-[34px]',
        'iak/design-system/no-raw-hex' => '#ffffff',
        'iak/design-system/no-primitive-color' => 'bg-blue-500',
    ] as $rule => $hit) {
        $violation = AuditCommandTestHelper::violation($payload, $rule);

        expect($violation)->not->toBeNull()
            ->and($violation['file'])->toBe('resources/js/features/vehicles/vehicle-table.tsx')
            ->and($violation['hit'])->toBe($hit)
            ->and(is_int($violation['line']))->toBeTrue()
            ->and(is_int($violation['column']))->toBeTrue()
            ->and(str_starts_with((string) $violation['fingerprint'], 'sha256:'))->toBeTrue();
    }
});

test('reports forbidden top-level behavior folders while allowing generated actions', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);
    AuditCommandTestHelper::write($base, 'resources/js/hooks/use-vehicles.ts', 'export const useVehicles = () => null;');
    AuditCommandTestHelper::write($base, 'resources/js/actions/vehicles.ts', 'export const storeVehicle = () => null;');
    AuditCommandTestHelper::write($base, 'resources/js/actions/generated/vehicles.ts', 'export const generated = () => null;');

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_forbidden_folders');
    $files = array_column(array_filter(
        $payload['violations'],
        static fn (array $violation): bool => $violation['rule'] === 'iak/role/no-top-level-behavior-folder'
    ), 'file');

    expect($exitCode)->toBe(1)
        ->and($files)->toContain('resources/js/actions/vehicles.ts')
        ->and($files)->toContain('resources/js/hooks/use-vehicles.ts')
        ->and($files)->not->toContain('resources/js/actions/generated/vehicles.ts');
});

test('reports missing required ui app and feature stories', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);

    unlink($base.'/resources/js/components/ui/button.stories.tsx');
    unlink($base.'/resources/js/components/app/filter-bar.stories.tsx');
    unlink($base.'/resources/js/features/vehicles/vehicle-table.stories.tsx');

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_missing_stories');

    expect($exitCode)->toBe(1)
        ->and(AuditCommandTestHelper::violation($payload, 'iak/stories/required-ui')['hit'])->toBe('resources/js/components/ui/button.stories.tsx')
        ->and(AuditCommandTestHelper::violation($payload, 'iak/stories/required-app')['hit'])->toBe('resources/js/components/app/filter-bar.stories.tsx')
        ->and(AuditCommandTestHelper::violation($payload, 'iak/stories/required-feature')['hit'])->toBe('resources/js/features/vehicles/vehicle-table.stories.tsx');
});

test('reports feature type files missing generated contract imports', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);
    AuditCommandTestHelper::write($base, 'resources/js/features/vehicles/vehicle.types.ts', <<<'TS'
export type VehicleResource = { id: number; name: string };
TS);

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_missing_type_import');
    $violation = AuditCommandTestHelper::violation($payload, 'iak/types/generated-contract-import-required');

    expect($exitCode)->toBe(1)
        ->and($violation)->not->toBeNull()
        ->and($violation['file'])->toBe('resources/js/features/vehicles/vehicle.types.ts')
        ->and($violation['hit'])->toBe('@/types/generated');

    AuditCommandTestHelper::write($base, 'resources/js/features/vehicles/vehicle.types.ts', <<<'TS'
import type { App } from '@/types/generated';

export type VehicleResource = App.Data.VehicleData;
TS);

    [$fixedExitCode, $fixedPayload] = AuditCommandTestHelper::run('run_fixed_type_import');

    expect($fixedExitCode)->toBe(0)
        ->and($fixedPayload['violations'])->toBe([]);
});

test('returns blocked payload for invalid run identifier', function (): void {
    [$exitCode, $payload] = AuditCommandTestHelper::run('run/../bad', [
        '--config' => config_path('inertia-agent-kit.php'),
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.usage.invalid_run_id')
        ->and($payload['artifacts']['audit']['path'])->toBe('.iak/runs/run_invalid/audit.json');
});

test('returns blocked payload for missing config files', function (): void {
    [$exitCode, $payload] = AuditCommandTestHelper::run('run_missing_config', [
        '--config' => 'config/missing.php',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.config.load_failed');
});

test('returns blocked payload for invalid config contents', function (): void {
    $base = $this->base;
    mkdir($base.'/config', 0755, true);
    file_put_contents($base.'/config/faulty-audit.php', '<?php return "invalid";');

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_invalid_config', [
        '--config' => 'config/faulty-audit.php',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.config.load_failed');
});

test('returns blocked payload when configuration shape is invalid', function (): void {
    $base = $this->base;
    mkdir($base.'/config', 0755, true);
    file_put_contents($base.'/config/invalid-shape.php', '<?php return ["paths" => "bad"];');

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_invalid_shape', [
        '--config' => 'config/invalid-shape.php',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.config.invalid');
});

test('returns plain text output when json output is disabled', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);

    $exitCode = Artisan::call('iak:audit', [
        '--run-id' => 'run_plain',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Audit passed: 0 errors.')
        ->and($output)->toContain('Artifact: .iak/runs/run_plain/audit.json');
});

test('returns blocked output when generated config section is invalid', function (): void {
    $base = $this->base;

    AuditCommandTestHelper::writeCleanFixture($base);
    mkdir($base.'/config', 0755, true);
    $config = AuditTestHelper::config($base);
    $config['generated'] = null;
    $config['forbidden_folders'] = ['hooks'];

    $path = 'config/invalid-generated.php';
    file_put_contents($base.'/'.$path, '<?php return '.var_export($config, true).';');

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_invalid_generated', [
        '--config' => $path,
    ]);

    $validationErrors = $payload['errors'][0]['context']['errors'] ?? [];
    $validationCodes = array_column($validationErrors, 'code');

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.config.invalid')
        ->and($validationCodes)->toContain('iak.config.generated.type_alias_invalid')
        ->and($validationCodes)->toContain('iak.config.generated.types_invalid')
        ->and($validationCodes)->toContain('iak.config.generated.routes_invalid')
        ->and($validationCodes)->toContain('iak.config.generated.actions_invalid');
});

test('returns blocked output when forbidden folders config is invalid', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);
    mkdir($base.'/config', 0755, true);
    $config = AuditTestHelper::config($base);
    $config['forbidden_folders'] = 'hooks';

    $path = 'config/invalid-forbidden.php';
    file_put_contents($base.'/'.$path, '<?php return '.var_export($config, true).';');

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_invalid_forbidden', [
        '--config' => $path,
    ]);

    $validationErrors = $payload['errors'][0]['context']['errors'] ?? [];
    $validationCodes = array_column($validationErrors, 'code');

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.config.invalid')
        ->and($validationCodes)->toContain('iak.config.forbidden_folders_invalid');
});

test('returns blocked output when audit payload cannot be encoded as json', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);

    $path = 'config/unencodable.php';
    mkdir($base.'/config', 0755, true);
    file_put_contents($base.'/'.$path, <<<'PHP'
<?php
return [
    'paths' => [
        'root' => 'resources/js',
        'features' => 'resources/js/features',
        'components_ui' => 'resources/js/components/ui',
        'components_app' => 'resources/js/components/app',
        'runs' => '.iak/runs',
        'layouts' => 'resources/js/layouts',
        'css' => 'resources/css/iak',
    ],
    'generated' => [
        'type_alias' => '@/types/generated',
        'types' => 'resources/js/types/generated/index.d.ts',
        'routes' => 'resources/js/routes/generated',
        'actions' => 'resources/js/actions/generated',
    ],
    'audit' => [
        'rules' => [
            'no_raw_palette_or_arbitrary_values' => [
                'ignore_files' => ['resources/css/iak/tokens.css'],
            ],
        ],
    ],
    'forbidden_folders' => ['hooks'],
    'extra' => fopen(__FILE__, 'r'),
];
PHP
    );

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_json_error', [
        '--config' => $path,
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.json.encode_failed')
        ->and($payload['summary'])->toContain('blocked');
});

test('defaults adapter metadata to react when config adapter is empty', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);

    config(['inertia-agent-kit.adapter' => '']);

    [$exitCode, $payload] = AuditCommandTestHelper::run('run_default_adapter');

    expect($exitCode)->toBe(0)
        ->and($payload['meta']['adapter'])->toBe('laravel-inertia-react');
});

test('uses a generated run id when one is not provided', function (): void {
    AuditCommandTestHelper::writeCleanFixture($this->base);

    $exitCode = Artisan::call('iak:audit', [
        '--json' => true,
    ]);
    $payload = AuditCommandTestHelper::lastJsonOutput();

    $runId = $payload['runId'] ?? '';

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('passed')
        ->and($runId)->toMatch('/^run_[a-z0-9-]+$/i')
        ->and($payload['artifacts']['audit']['path'])->toStartWith('.iak/runs/')
        ->and(base_path($payload['artifacts']['audit']['path']))->toBeFile();
});

test('pretty prints audit json when requested', function (): void {
    $base = $this->base;
    AuditCommandTestHelper::writeCleanFixture($base);

    $exitCode = Artisan::call('iak:audit', [
        '--json' => true,
        '--pretty' => true,
        '--run-id' => 'run_pretty',
    ]);

    $output = Artisan::output();
    $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('passed')
        ->and($output)->toContain(PHP_EOL.'    "event": "iak.audit.completed"');
});
