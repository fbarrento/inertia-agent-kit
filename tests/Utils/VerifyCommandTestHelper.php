<?php

declare(strict_types=1);

namespace Tests\Utils;

use FilesystemIterator;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class VerifyCommandTestHelper
{
    public function __construct(private string $basePath) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function call(array $arguments = []): array
    {
        [$exitCode, $output] = $this->callRaw($arguments);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return [$exitCode, $payload];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: int, 1: string}
     */
    public function callRaw(array $arguments = [], bool $json = true): array
    {
        if ($json) {
            $arguments = [
                ...$arguments,
                '--json' => true,
            ];
        }

        $exitCode = Artisan::call('iak:verify', $arguments);

        return [$exitCode, Artisan::output()];
    }

    public function cleanFixture(): void
    {
        $this->write('resources/js/components/ui/button.tsx', <<<'TSX'
export function Button() {
    return <button className="bg-ds-surface text-ds-body border-ds-border">Save</button>;
}
TSX);

        $this->write('resources/js/components/ui/button.stories.tsx', <<<'TSX'
import { Button } from './button';

export default { component: Button };
export const Default = {};
TSX);

        $this->write('resources/js/components/app/filter-bar.tsx', <<<'TSX'
export function FilterBar() {
    return <div className="bg-ds-panel text-ds-muted border-ds-border">Filters</div>;
}
TSX);

        $this->write('resources/js/components/app/filter-bar.stories.tsx', <<<'TSX'
import { FilterBar } from './filter-bar';

export default { component: FilterBar };
export const Default = {};
TSX);

        $this->write('resources/js/features/vehicles/vehicle-table.tsx', <<<'TSX'
import type { VehicleResource } from './vehicle.types';

export function VehicleTable({ vehicles }: { vehicles: VehicleResource[] }) {
    return <section className="bg-ds-surface text-ds-body border-ds-border">{vehicles.length}</section>;
}
TSX);

        $this->write('resources/js/features/vehicles/vehicle-table.stories.tsx', <<<'TSX'
import { VehicleTable } from './vehicle-table';

export default { component: VehicleTable };
export const Default = { args: { vehicles: [] } };
TSX);

        $this->write('resources/js/features/vehicles/vehicle-form.tsx', <<<'TSX'
export function VehicleForm() {
    return <form className="bg-ds-surface text-ds-body border-ds-border" />;
}
TSX);

        $this->write('resources/js/features/vehicles/vehicle-form.stories.tsx', <<<'TSX'
import { VehicleForm } from './vehicle-form';

export default { component: VehicleForm };
export const Default = {};
TSX);

        $this->write('resources/js/features/vehicles/vehicle.types.ts', <<<'TS'
import type { App } from '@/types/generated';

export type VehicleResource = App.Data.VehicleData;
TS);

        $this->write('resources/js/types/generated/index.d.ts', <<<'TS'
export namespace App {
    export namespace Data {
        export type VehicleData = { id: number; name: string };
    }
}
TS);

        $this->write('resources/css/iak/tokens.css', <<<'CSS'
:root {
    --ds-color-surface: #ffffff;
}
CSS);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function writeFeedback(array $overrides = []): array
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
            'tags' => [
                'pattern',
                'filter-bar',
            ],
            'attachments' => [
                'screenshot' => ".iak/feedback/{$id}/screenshot.png",
                'dom' => ".iak/feedback/{$id}/dom.html",
                'console' => ".iak/feedback/{$id}/console.json",
                'network' => null,
                'trace' => null,
            ],
            'context' => [
                'gitSha' => null,
                'branch' => 'feat/feedback',
                'adapter' => 'laravel-inertia-react',
                'componentCandidates' => [
                    'FilterBar',
                ],
                'storyArgs' => null,
                'testRunId' => null,
            ],
            'resolution' => null,
            'createdAt' => '2026-05-22T15:00:00Z',
            'updatedAt' => '2026-05-22T15:00:00Z',
        ], $overrides);

        $this->writeJson(".iak/feedback/{$id}/record.json", $record);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function auditArtifact(array $overrides = []): array
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
                'configHash' => $this->configHash(),
            ],
        ], $overrides);
    }

    public function configHash(): string
    {
        return 'sha256:'.hash('sha256', json_encode(config('inertia-agent-kit'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function writeJson(string $relativePath, array $payload): void
    {
        $this->write(
            $relativePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    public function write(string $relativePath, string $contents): void
    {
        $path = $this->basePath.'/'.$relativePath;
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);
    }

    /**
     * @return array<string, mixed>
     */
    public function readJson(string $relativePath): array
    {
        $payload = json_decode((string) file_get_contents($this->basePath.'/'.$relativePath), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new \RuntimeException('Expected array payload from verify command test helper.');
        }

        return $payload;
    }

    public function removeDirectory(): void
    {
        if (! is_dir($this->basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->basePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($this->basePath);
    }
}
