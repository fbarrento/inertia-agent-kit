<?php

declare(strict_types=1);

namespace Tests\Utils;

use Illuminate\Support\Facades\Artisan;

final class AuditCommandTestHelper
{
    public static function tempBasePath(): string
    {
        $path = sys_get_temp_dir().'/iak-audit-'.bin2hex(random_bytes(6));

        mkdir($path, 0755, true);

        return $path;
    }

    public static function useTempBase(object $app): string
    {
        $base = self::tempBasePath();
        $app->setBasePath($base);

        return $base;
    }

    public static function write(string $base, string $path, string $contents): void
    {
        $absolute = $base.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);
        $directory = dirname($absolute);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolute, $contents);
    }

    public static function writeCleanFixture(string $base): void
    {
        self::write($base, 'resources/js/components/ui/button.tsx', <<<'TSX'
export function Button() {
    return <button className="bg-ds-surface text-ds-body border-ds-border">Save</button>;
}
TSX);

        self::write($base, 'resources/js/components/ui/button.stories.tsx', <<<'TSX'
import { Button } from './button';

export default { component: Button };
export const Default = {};
TSX);

        self::write($base, 'resources/js/components/app/filter-bar.tsx', <<<'TSX'
export function FilterBar() {
    return <div className="bg-ds-panel text-ds-muted border-ds-border">Filters</div>;
}
TSX);

        self::write($base, 'resources/js/components/app/filter-bar.stories.tsx', <<<'TSX'
import { FilterBar } from './filter-bar';

export default { component: FilterBar };
export const Default = {};
TSX);

        self::write($base, 'resources/js/features/vehicles/vehicle-table.tsx', <<<'TSX'
import type { VehicleResource } from './vehicle.types';

export function VehicleTable({ vehicles }: { vehicles: VehicleResource[] }) {
    return <section className="bg-ds-surface text-ds-body border-ds-border">{vehicles.length}</section>;
}
TSX);

        self::write($base, 'resources/js/features/vehicles/vehicle-table.stories.tsx', <<<'TSX'
import { VehicleTable } from './vehicle-table';

export default { component: VehicleTable };
export const Default = { args: { vehicles: [] } };
TSX);

        self::write($base, 'resources/js/features/vehicles/vehicle-form.tsx', <<<'TSX'
export function VehicleForm() {
    return <form className="bg-ds-surface text-ds-body border-ds-border" />;
}
TSX);

        self::write($base, 'resources/js/features/vehicles/vehicle-form.stories.tsx', <<<'TSX'
import { VehicleForm } from './vehicle-form';

export default { component: VehicleForm };
export const Default = {};
TSX);

        self::write($base, 'resources/js/features/vehicles/vehicle.types.ts', <<<'TS'
import type { App } from '@/types/generated';

export type VehicleResource = App.Data.VehicleData;
TS);

        self::write($base, 'resources/js/types/generated/index.d.ts', <<<'TS'
export namespace App {
    export namespace Data {
        export type VehicleData = { id: number; name: string };
    }
}
TS);

        self::write($base, 'resources/css/iak/tokens.css', <<<'CSS'
:root {
    --ds-color-surface: #ffffff;
}
CSS);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: int, 1: array<string, mixed>}
     */
    public static function run(string $runId, array $arguments = []): array
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
     * @return array<string, mixed>
     */
    public static function lastJsonOutput(): array
    {
        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function violation(array $payload, string $rule): ?array
    {
        foreach ($payload['violations'] as $violation) {
            if ($violation['rule'] === $rule) {
                return $violation;
            }
        }

        return null;
    }
}
