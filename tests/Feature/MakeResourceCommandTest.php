<?php

declare(strict_types=1);

use InertiaAgentKit\Console\MakeResourceCommand;
use Symfony\Component\Console\Input\InputInterface;
use Tests\Utils\MakeResourceCommandTestHelper;

beforeEach(function (): void {
    $this->basePath = MakeResourceCommandTestHelper::fixtureBasePath();
});

test('registers the iak:make-resource command', function (): void {
    expect(Artisan::all())->toHaveKey('iak:make-resource');
});

test('emits one valid json object with the scaffold plan schema', function (): void {
    $basePath = $this->basePath;
    MakeResourceCommandTestHelper::writeGeneratedTypes($basePath);

    $exitCode = Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--json' => true,
    ]);

    $payload = MakeResourceCommandTestHelper::jsonOutput();

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

test('scaffolds vehicles resource pages and feature files as react', function (): void {
    $basePath = $this->basePath;
    MakeResourceCommandTestHelper::writeGeneratedTypes($basePath);

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

test('supports dry-run without writing files', function (): void {
    $basePath = $this->basePath;
    MakeResourceCommandTestHelper::writeGeneratedTypes($basePath);

    $exitCode = Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--dry-run' => true,
        '--json' => true,
    ]);

    $payload = MakeResourceCommandTestHelper::jsonOutput();

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('planned')
        ->and($payload['mode'])->toBe('dry-run')
        ->and($payload['writtenFiles'])->toBe([])
        ->and(is_file($basePath.'/resources/js/pages/vehicles/index.tsx'))->toBeFalse()
        ->and(is_file($basePath.'/resources/js/features/vehicles/vehicle.types.ts'))->toBeFalse();
});

test('does not create top-level forbidden folders', function (): void {
    $basePath = $this->basePath;
    MakeResourceCommandTestHelper::writeGeneratedTypes($basePath);

    Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--json' => true,
    ]);

    foreach (['queries', 'actions', 'forms', 'hooks', 'composables'] as $folder) {
        expect(is_dir($basePath.'/resources/js/'.$folder))->toBeFalse();
    }
});

test('imports generated App types from the configured generated path', function (): void {
    $basePath = $this->basePath;

    config()->set('inertia-agent-kit.generated.type_alias', '@/types/contracts');
    MakeResourceCommandTestHelper::writeGeneratedTypes($basePath);

    Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--json' => true,
    ]);

    $payload = MakeResourceCommandTestHelper::jsonOutput();
    $types = file_get_contents($basePath.'/resources/js/features/vehicles/vehicle.types.ts');

    expect($payload['generatedTypeImports'][0]['from'])->toBe('@/types/contracts')
        ->and($types)->toContain("import type { App } from '@/types/contracts'")
        ->and($types)->toContain('App.Data.Vehicles.VehicleIndexPageData')
        ->and($types)->not->toContain('interface Vehicle');
});

test('allows missing generated types without writing fallback DTOs', function (): void {
    $basePath = $this->basePath;

    $exitCode = Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
        '--allow-missing-generated-types' => true,
        '--json' => true,
    ]);

    $payload = MakeResourceCommandTestHelper::jsonOutput();
    $types = file_get_contents($basePath.'/resources/js/features/vehicles/vehicle.types.ts');

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('completed')
        ->and($types)->toContain("import type { App } from '@/types/generated'")
        ->and($types)->toContain('App.Data.Vehicles.VehicleFormData')
        ->and($types)->not->toContain('interface Vehicle')
        ->and(is_file($basePath.'/resources/js/types/generated/index.d.ts'))->toBeFalse();
});

test('uses semantic ds utilities and no raw hex or primitive color utilities in stubs', function (): void {
    $stubFiles = MakeResourceCommandTestHelper::stubFiles();

    expect($stubFiles)->not->toBeEmpty();

    foreach ($stubFiles as $stubFile) {
        $contents = file_get_contents($stubFile) ?: '';

        expect($contents)
            ->not->toMatch('/#[0-9a-fA-F]{3,8}\b/')
            ->not->toMatch('/\b(?:bg|text|border)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)\b/');

    }

    $tsxStubContents = array_map(
        static fn (string $stubFile): string => str_ends_with($stubFile, '.tsx.stub') ? (file_get_contents($stubFile) ?: '') : '',
        $stubFiles,
    );

    expect(implode("\n", $tsxStubContents))->toContain('ds-');
});

test('returns plain text summary when json flag is disabled', function (): void {
    $basePath = $this->basePath;
    MakeResourceCommandTestHelper::writeGeneratedTypes($basePath);

    $exitCode = Artisan::call('iak:make-resource', [
        'resource' => 'vehicles',
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Scaffolded vehicles resource.');
});

test('returns first error in plain text when scaffold fails', function (): void {
    $exitCode = Artisan::call('iak:make-resource', [
        '--adapter' => 'vue',
    ]);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain('Only the react adapter is supported in this package port.');
});

test('normalizes resource argument for non-scalar values', function (): void {
    $command = app(MakeResourceCommand::class);
    $input = Mockery::mock(InputInterface::class);

    $input->shouldReceive('getArgument')->with('resource')->andReturn(['vehicles']);
    $input->shouldReceive('getOption')->withAnyArgs()->andReturnNull();

    $inputProperty = new ReflectionProperty($command, 'input');
    $inputProperty->setValue($command, $input);

    $method = new ReflectionMethod($command, 'nullableArgument');

    expect($method->invoke($command, 'resource'))->toBeNull();
});

test('normalizes option values for non-scalar values', function (): void {
    $command = app(MakeResourceCommand::class);
    $input = Mockery::mock(InputInterface::class);

    $input->shouldReceive('getOption')->with('controller')->andReturn(['App\\\\Http\\\\Controllers\\\\VehicleController']);
    $input->shouldReceive('getArgument')->withAnyArgs()->andReturnNull();

    $inputProperty = new ReflectionProperty($command, 'input');
    $inputProperty->setValue($command, $input);

    $method = new ReflectionMethod($command, 'nullableOption');

    expect($method->invoke($command, 'controller'))->toBeNull();
});

test('normalizes option values by trimming whitespace', function (): void {
    $command = app(MakeResourceCommand::class);
    $input = Mockery::mock(InputInterface::class);

    $input->shouldReceive('getOption')->with('adapter')->andReturn('  react  ');
    $input->shouldReceive('getArgument')->withAnyArgs()->andReturnNull();

    $inputProperty = new ReflectionProperty($command, 'input');
    $inputProperty->setValue($command, $input);

    $method = new ReflectionMethod($command, 'nullableOption');

    expect($method->invoke($command, 'adapter'))->toBe('react');
});
