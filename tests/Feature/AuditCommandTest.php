<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function iak_audit_temp_base(): string
{
    $path = sys_get_temp_dir().'/iak-audit-'.bin2hex(random_bytes(6));

    mkdir($path, 0755, true);

    return $path;
}

function iak_audit_use_temp_base(object $app): string
{
    $base = iak_audit_temp_base();
    $app->setBasePath($base);

    return $base;
}

function iak_audit_write(string $base, string $path, string $contents): void
{
    $absolute = $base.'/'.$path;

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0755, true);
    }

    file_put_contents($absolute, $contents);
}

function iak_audit_clean_fixture(string $base): void
{
    iak_audit_write($base, 'resources/js/components/ui/button.tsx', <<<'TSX'
export function Button() {
    return <button className="bg-ds-surface text-ds-body border-ds-border">Save</button>;
}
TSX);

    iak_audit_write($base, 'resources/js/components/ui/button.stories.tsx', <<<'TSX'
import { Button } from './button';

export default { component: Button };
export const Default = {};
TSX);

    iak_audit_write($base, 'resources/js/components/app/filter-bar.tsx', <<<'TSX'
export function FilterBar() {
    return <div className="bg-ds-panel text-ds-muted border-ds-border">Filters</div>;
}
TSX);

    iak_audit_write($base, 'resources/js/components/app/filter-bar.stories.tsx', <<<'TSX'
import { FilterBar } from './filter-bar';

export default { component: FilterBar };
export const Default = {};
TSX);

    iak_audit_write($base, 'resources/js/features/vehicles/vehicle-table.tsx', <<<'TSX'
import type { VehicleResource } from './vehicle.types';

export function VehicleTable({ vehicles }: { vehicles: VehicleResource[] }) {
    return <section className="bg-ds-surface text-ds-body border-ds-border">{vehicles.length}</section>;
}
TSX);

    iak_audit_write($base, 'resources/js/features/vehicles/vehicle-table.stories.tsx', <<<'TSX'
import { VehicleTable } from './vehicle-table';

export default { component: VehicleTable };
export const Default = { args: { vehicles: [] } };
TSX);

    iak_audit_write($base, 'resources/js/features/vehicles/vehicle-form.tsx', <<<'TSX'
export function VehicleForm() {
    return <form className="bg-ds-surface text-ds-body border-ds-border" />;
}
TSX);

    iak_audit_write($base, 'resources/js/features/vehicles/vehicle-form.stories.tsx', <<<'TSX'
import { VehicleForm } from './vehicle-form';

export default { component: VehicleForm };
export const Default = {};
TSX);

    iak_audit_write($base, 'resources/js/features/vehicles/vehicle.types.ts', <<<'TS'
import type { App } from '@/types/generated';

export type VehicleResource = App.Data.VehicleData;
TS);

    iak_audit_write($base, 'resources/js/types/generated/index.d.ts', <<<'TS'
export namespace App {
    export namespace Data {
        export type VehicleData = { id: number; name: string };
    }
}
TS);

    iak_audit_write($base, 'resources/css/iak/tokens.css', <<<'CSS'
:root {
    --ds-color-surface: #ffffff;
}
CSS);
}

/**
 * @param array<string, mixed> $arguments
 *
 * @return array{0: int, 1: array<string, mixed>}
 */
function iak_audit_run(string $runId, array $arguments = []): array
{
    $exitCode = Artisan::call('iak:audit', [
        '--json' => true,
        '--run-id' => $runId,
        ...$arguments,
    ]);

    return [
        $exitCode,
        json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR),
    ];
}

/**
 * @param array<string, mixed> $payload
 *
 * @return array<string, mixed>|null
 */
function iak_audit_violation(array $payload, string $rule): ?array
{
    foreach ($payload['violations'] as $violation) {
        if ($violation['rule'] === $rule) {
            return $violation;
        }
    }

    return null;
}

it('passes a clean scaffold-like fixture', function (): void {
    $base = iak_audit_use_temp_base($this->app);
    iak_audit_clean_fixture($base);

    [$exitCode, $payload] = iak_audit_run('run_clean');

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.audit.v1')
        ->and($payload['event'])->toBe('iak.audit.completed')
        ->and($payload['status'])->toBe('passed')
        ->and($payload['totals']['errors'])->toBe(0)
        ->and($payload['violations'])->toBe([])
        ->and($payload['artifacts']['audit']['path'])->toBe('.iak/runs/run_clean/audit.json');
});

it('reports design-system violations with locations and fingerprints', function (): void {
    $base = iak_audit_use_temp_base($this->app);
    iak_audit_clean_fixture($base);
    iak_audit_write($base, 'resources/js/features/vehicles/vehicle-table.tsx', <<<'TSX'
export function VehicleTable() {
    const swatch = '#ffffff';

    return <section className="p-[34px] bg-blue-500 text-ds-body">{swatch}</section>;
}
TSX);

    [$exitCode, $payload] = iak_audit_run('run_design_violations');
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
        $violation = iak_audit_violation($payload, $rule);

        expect($violation)->not->toBeNull()
            ->and($violation['file'])->toBe('resources/js/features/vehicles/vehicle-table.tsx')
            ->and($violation['hit'])->toBe($hit)
            ->and(is_int($violation['line']))->toBeTrue()
            ->and(is_int($violation['column']))->toBeTrue()
            ->and(str_starts_with($violation['fingerprint'], 'sha256:'))->toBeTrue();
    }
});

it('reports forbidden top-level behavior folders while allowing generated actions', function (): void {
    $base = iak_audit_use_temp_base($this->app);
    iak_audit_clean_fixture($base);
    iak_audit_write($base, 'resources/js/hooks/use-vehicles.ts', 'export const useVehicles = () => null;');
    iak_audit_write($base, 'resources/js/actions/vehicles.ts', 'export const storeVehicle = () => null;');
    iak_audit_write($base, 'resources/js/actions/generated/vehicles.ts', 'export const generated = () => null;');

    [$exitCode, $payload] = iak_audit_run('run_forbidden_folders');
    $files = array_column(array_filter(
        $payload['violations'],
        static fn (array $violation): bool => $violation['rule'] === 'iak/role/no-top-level-behavior-folder'
    ), 'file');

    expect($exitCode)->toBe(1)
        ->and($files)->toContain('resources/js/actions/vehicles.ts')
        ->and($files)->toContain('resources/js/hooks/use-vehicles.ts')
        ->and($files)->not->toContain('resources/js/actions/generated/vehicles.ts');
});

it('reports missing required ui app and feature stories', function (): void {
    $base = iak_audit_use_temp_base($this->app);
    iak_audit_clean_fixture($base);

    unlink($base.'/resources/js/components/ui/button.stories.tsx');
    unlink($base.'/resources/js/components/app/filter-bar.stories.tsx');
    unlink($base.'/resources/js/features/vehicles/vehicle-table.stories.tsx');

    [$exitCode, $payload] = iak_audit_run('run_missing_stories');

    expect($exitCode)->toBe(1)
        ->and(iak_audit_violation($payload, 'iak/stories/required-ui')['hit'])
        ->toBe('resources/js/components/ui/button.stories.tsx')
        ->and(iak_audit_violation($payload, 'iak/stories/required-app')['hit'])
        ->toBe('resources/js/components/app/filter-bar.stories.tsx')
        ->and(iak_audit_violation($payload, 'iak/stories/required-feature')['hit'])
        ->toBe('resources/js/features/vehicles/vehicle-table.stories.tsx');
});

it('reports feature type files missing generated contract imports', function (): void {
    $base = iak_audit_use_temp_base($this->app);
    iak_audit_clean_fixture($base);
    iak_audit_write($base, 'resources/js/features/vehicles/vehicle.types.ts', <<<'TS'
export type VehicleResource = { id: number; name: string };
TS);

    [$exitCode, $payload] = iak_audit_run('run_missing_type_import');
    $violation = iak_audit_violation($payload, 'iak/types/generated-contract-import-required');

    expect($exitCode)->toBe(1)
        ->and($violation)->not->toBeNull()
        ->and($violation['file'])->toBe('resources/js/features/vehicles/vehicle.types.ts')
        ->and($violation['hit'])->toBe('@/types/generated');

    iak_audit_write($base, 'resources/js/features/vehicles/vehicle.types.ts', <<<'TS'
import type { App } from '@/types/generated';

export type VehicleResource = App.Data.VehicleData;
TS);

    [$fixedExitCode, $fixedPayload] = iak_audit_run('run_fixed_type_import');

    expect($fixedExitCode)->toBe(0)
        ->and($fixedPayload['violations'])->toBe([]);
});

it('writes an audit artifact matching stdout json', function (): void {
    $base = iak_audit_use_temp_base($this->app);
    iak_audit_clean_fixture($base);

    [$exitCode, $payload] = iak_audit_run('run_artifact');
    $artifact = json_decode(
        file_get_contents($base.'/'.$payload['artifacts']['audit']['path']) ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($exitCode)->toBe(0)
        ->and($artifact)->toEqual($payload);
});

it('returns a structured blocked result for invalid config', function (): void {
    $base = iak_audit_use_temp_base($this->app);
    iak_audit_clean_fixture($base);
    iak_audit_write($base, 'invalid-iak.php', <<<'PHP'
<?php

return 'invalid';
PHP);

    [$exitCode, $payload] = iak_audit_run('run_invalid_config', [
        '--config' => 'invalid-iak.php',
    ]);
    $artifact = json_decode(
        file_get_contents($base.'/'.$payload['artifacts']['audit']['path']) ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.config.load_failed')
        ->and($artifact)->toEqual($payload);
});
