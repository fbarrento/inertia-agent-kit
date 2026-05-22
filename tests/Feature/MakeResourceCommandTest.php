<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('registers the iak:make-resource command', function (): void {
    expect(Artisan::all())->toHaveKey('iak:make-resource');
});

it('emits one valid json object with the scaffold plan schema', function (): void {
    $basePath = iakFixtureBasePath();
    iakWriteGeneratedTypes($basePath);

    $exitCode = Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--json' => true,
    ]);

    $payload = iakJsonOutput();

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.scaffold-plan.v1')
        ->and($payload['status'])->toBe('completed')
        ->and($payload['resource'])->toMatchArray([
            'name' => 'vehicles',
            'singular' => 'vehicle',
            'folder' => 'vehicles',
            'routeName' => 'vehicles',
            'controller' => 'App\\Http\\Controllers\\VehicleController',
        ])
        ->and($payload['controllerMap'])->toHaveCount(4)
        ->and($payload['generatedTypeImports'][0])->toMatchArray([
            'from' => '@/types/generated',
            'symbols' => ['App'],
            'usedBy' => 'resources/js/features/vehicles/vehicle.types.ts',
        ])
        ->and($payload['stories'])->toHaveCount(2)
        ->and($payload['writtenFiles'])->toHaveCount(12)
        ->and($payload['skippedFiles'])->toBe([])
        ->and($payload['conflicts'])->toBe([])
        ->and($payload['errors'])->toBe([]);
});

it('scaffolds vehicles resource pages and feature files as react', function (): void {
    $basePath = iakFixtureBasePath();
    iakWriteGeneratedTypes($basePath);

    Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--json' => true,
    ]);

    foreach ([
        'resources/js/pages/vehicles/index.tsx',
        'resources/js/pages/vehicles/show.tsx',
        'resources/js/pages/vehicles/create.tsx',
        'resources/js/pages/vehicles/edit.tsx',
        'resources/js/features/vehicles/vehicle.types.ts',
        'resources/js/features/vehicles/vehicle.fixtures.ts',
        'resources/js/features/vehicles/vehicle-table.tsx',
        'resources/js/features/vehicles/vehicle-table.stories.tsx',
        'resources/js/features/vehicles/vehicle-filters.tsx',
        'resources/js/features/vehicles/vehicle-form.tsx',
        'resources/js/features/vehicles/vehicle-form.stories.tsx',
        'resources/js/features/vehicles/vehicle-empty-state.tsx',
    ] as $path) {
        expect(is_file($basePath.'/'.$path))->toBeTrue();
    }

    expect(file_get_contents($basePath.'/resources/js/pages/vehicles/index.tsx'))
        ->toContain("from '@/features/vehicles/vehicle.types'")
        ->toContain('VehicleTable')
        ->and(file_get_contents($basePath.'/resources/js/features/vehicles/vehicle-table.stories.tsx'))
        ->toContain("from './vehicle.fixtures'")
        ->toContain('Default')
        ->toContain('Empty')
        ->toContain('Loading')
        ->toContain('Error');
});

it('supports dry-run without writing files', function (): void {
    $basePath = iakFixtureBasePath();
    iakWriteGeneratedTypes($basePath);

    $exitCode = Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--dry-run' => true,
        '--json' => true,
    ]);

    $payload = iakJsonOutput();

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('planned')
        ->and($payload['mode'])->toBe('dry-run')
        ->and($payload['writtenFiles'])->toBe([])
        ->and(is_file($basePath.'/resources/js/pages/vehicles/index.tsx'))->toBeFalse()
        ->and(is_file($basePath.'/resources/js/features/vehicles/vehicle.types.ts'))->toBeFalse();
});

it('does not create top-level forbidden folders', function (): void {
    $basePath = iakFixtureBasePath();
    iakWriteGeneratedTypes($basePath);

    Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--json' => true,
    ]);

    foreach (['queries', 'actions', 'forms', 'hooks', 'composables'] as $folder) {
        expect(is_dir($basePath.'/resources/js/'.$folder))->toBeFalse();
    }
});

it('imports generated App types from the configured generated path', function (): void {
    $basePath = iakFixtureBasePath();

    config()->set('inertia-agent-kit.generated.type_alias', '@/types/contracts');
    iakWriteGeneratedTypes($basePath);

    Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--json' => true,
    ]);

    $payload = iakJsonOutput();
    $types = file_get_contents($basePath.'/resources/js/features/vehicles/vehicle.types.ts');

    expect($payload['generatedTypeImports'][0]['from'])->toBe('@/types/contracts')
        ->and($types)->toContain("import type { App } from '@/types/contracts'")
        ->and($types)->toContain('App.Data.Vehicles.VehicleIndexPageData')
        ->and($types)->not->toContain('interface Vehicle');
});

it('allows missing generated types without writing fallback DTOs', function (): void {
    $basePath = iakFixtureBasePath();

    $exitCode = Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--allow-missing-generated-types' => true,
        '--json' => true,
    ]);

    $payload = iakJsonOutput();
    $types = file_get_contents($basePath.'/resources/js/features/vehicles/vehicle.types.ts');

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('completed')
        ->and($types)->toContain("import type { App } from '@/types/generated'")
        ->and($types)->toContain('App.Data.Vehicles.VehicleFormData')
        ->and($types)->not->toContain('interface Vehicle')
        ->and(is_file($basePath.'/resources/js/types/generated/index.d.ts'))->toBeFalse();
});

it('uses semantic ds utilities and no raw hex or primitive color utilities in stubs', function (): void {
    $stubFiles = iakStubFiles();

    expect($stubFiles)->not->toBeEmpty();

    foreach ($stubFiles as $stubFile) {
        $contents = file_get_contents($stubFile) ?: '';

        expect($contents)
            ->not->toMatch('/#[0-9a-fA-F]{3,8}\\b/')
            ->not->toMatch('/\\b(?:bg|text|border)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)\\b/');

    }

    $tsxStubContents = array_map(
        static fn (string $stubFile): string => str_ends_with($stubFile, '.tsx.stub') ? (file_get_contents($stubFile) ?: '') : '',
        $stubFiles,
    );

    expect(implode("\n", $tsxStubContents))->toContain('ds-');
});

function iakFixtureBasePath(): string
{
    $basePath = sys_get_temp_dir().'/iak-make-resource-'.bin2hex(random_bytes(6));

    mkdir($basePath.'/resources/js', 0755, true);

    app()->setBasePath($basePath);

    return $basePath;
}

function iakWriteGeneratedTypes(string $basePath): void
{
    $directory = $basePath.'/resources/js/types/generated';

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($directory.'/index.d.ts', <<<'TS'
export declare namespace App {
  export namespace Data {
    export namespace Vehicles {
      export type VehicleIndexPageData = Record<string, unknown>
      export type VehicleShowPageData = Record<string, unknown>
      export type VehicleCreatePageData = Record<string, unknown>
      export type VehicleEditPageData = Record<string, unknown>
      export type VehicleListItemData = Record<string, unknown>
      export type VehicleFormData = Record<string, unknown>
      export type VehicleFiltersData = Record<string, unknown>
    }
  }
}
TS);
}

function iakJsonOutput(): array
{
    return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * @return array<int, string>
 */
function iakStubFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/stubs/react'));

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}
