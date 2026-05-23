<?php

declare(strict_types=1);

use InertiaAgentKit\Audit\Auditor;
use Tests\TestCase;
use Tests\Utils\AuditCommandTestHelper;
use Tests\Utils\AuditTestHelper;

uses(TestCase::class);

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir().'/iak-auditor-unit-'.bin2hex(random_bytes(6));

    mkdir($this->basePath, 0755, true);
    $this->app->setBasePath($this->basePath);
});

afterEach(function (): void {
    $directory = $this->basePath ?? null;

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

test('returns a passed result for a clean fixture', function (): void {
    $base = $this->basePath;
    AuditCommandTestHelper::writeCleanFixture($base);

    $result = (new Auditor($this->app))->run(AuditTestHelper::config($base));

    expect($result['status'])->toBe('passed')
        ->and($result['totals']['errors'])->toBe(0)
        ->and($result['checks'])->toHaveCount(8)
        ->and($result['violations'])->toBe([])
        ->and($result['nextActions'])->toBe([]);
});

test('skips files under generated artifact paths during design-system scans', function (): void {
    $base = $this->basePath;

    AuditCommandTestHelper::write($base, 'resources/js/types/generated/index.d.ts', <<<'TS'
export const style = 'bg-red-500 p-[12px] #fff';
TS);

    $result = (new Auditor($this->app))->run(AuditTestHelper::config($base));

    expect($result['status'])->toBe('passed')
        ->and($result['violations'])->toBe([]);
});

test('reports story, forbidden folder, and generated type contract violations together', function (): void {
    $base = $this->basePath;
    AuditCommandTestHelper::writeCleanFixture($base);
    AuditCommandTestHelper::write($base, 'resources/js/hooks/use-vehicles.ts', 'export const useVehicles = () => null;');
    AuditCommandTestHelper::write($base, 'resources/js/features/vehicles/vehicle-form.tsx', 'import type { VehicleFormData } from "./vehicle.types";');
    AuditCommandTestHelper::write($base, 'resources/js/features/vehicles/vehicle.types.ts', 'export type VehicleResource = { id: number };');
    unlink($base.'/resources/js/features/vehicles/vehicle-table.stories.tsx');

    $result = (new Auditor($this->app))->run(AuditTestHelper::config($base));
    $rules = array_column($result['violations'], 'rule');

    expect($result['status'])->toBe('failed')
        ->and($rules)->toContain('iak/role/no-top-level-behavior-folder')
        ->and($rules)->toContain('iak/stories/required-feature')
        ->and($rules)->toContain('iak/types/generated-contract-import-required');
});

test('collectFiles skips directories and skipped paths during discovery', function (): void {
    $base = $this->basePath;
    $auditor = new Auditor($this->app);

    mkdir($base.'/resources/js/pages', 0755, true);
    mkdir($base.'/resources/js/.git', 0755, true);
    file_put_contents($base.'/resources/js/pages/vehicles.tsx', 'export const Vehicles = [] as const;');
    file_put_contents($base.'/resources/js/.git/ignored.tsx', 'export const ignored = true;');
    symlink($base.'/resources/js/missing.tsx', $base.'/resources/js/broken-link.tsx');

    $collectFiles = new ReflectionMethod($auditor, 'collectFiles');
    $predicate = (static fn (string $path): bool => ! str_starts_with($path, 'resources/js/.git/')
        && ! str_contains($path, '/.git/'));

    $files = $collectFiles->invoke($auditor, ['resources/js'], $predicate);
    $paths = array_column($files, 'path');

    $isSkippedPath = new ReflectionMethod($auditor, 'isSkippedPath');

    expect($paths)
        ->toContain('resources/js/pages/vehicles.tsx')
        ->not->toContain('resources/js/.git/ignored.tsx')
        ->not->toContain('resources/js/broken-link.tsx')
        ->and($isSkippedPath->invoke($auditor, '.git'))->toBeTrue()
        ->and($isSkippedPath->invoke($auditor, 'resources/js/pages'))->toBeFalse();
});

test('identifies file role for pages, layouts and uses fallback relative import specifiers', function (): void {
    $base = $this->basePath;
    $auditor = new Auditor($this->app);

    mkdir($base.'/resources/js/pages', 0755, true);
    mkdir($base.'/resources/js/layouts', 0755, true);

    file_put_contents($base.'/resources/js/pages/reports.tsx', 'export default { status: "#fff" };');
    file_put_contents($base.'/resources/js/layouts/main.tsx', 'export default { status: "#fff" };');

    $result = $auditor->run(AuditTestHelper::config($base));

    $violations = array_filter($result['violations'], static fn (array $violation): bool => in_array($violation['file'], ['resources/js/pages/reports.tsx', 'resources/js/layouts/main.tsx'], true));

    $pageViolation = null;
    $layoutViolation = null;

    foreach ($violations as $violation) {
        if ($violation['file'] === 'resources/js/pages/reports.tsx') {
            $pageViolation = $violation;

            continue;
        }

        if ($violation['file'] === 'resources/js/layouts/main.tsx') {
            $layoutViolation = $violation;
        }
    }

    expect($pageViolation)
        ->toBeArray()
        ->and($pageViolation['role'])->toBe('page')
        ->and($pageViolation['resource'])->toBeNull()
        ->and($layoutViolation)
        ->toBeArray()
        ->and($layoutViolation['role'])->toBe('layout')
        ->and($layoutViolation['resource'])->toBeNull();

    $generatedImportSpecifiers = new ReflectionMethod($auditor, 'generatedImportSpecifiers');

    $config = AuditTestHelper::config($base);
    $config['generated']['types'] = null;
    $fallback = $generatedImportSpecifiers->invoke($auditor, 'resources/js/features/vehicles/vehicle.types.ts', $config);

    expect($fallback)
        ->toBeArray()
        ->toContain('@/types/generated')
        ->toHaveCount(3);

    $config['generated']['types'] = 'resources/js/types/generated/index.d.ts';
    $sameDirectory = $generatedImportSpecifiers->invoke($auditor, 'resources/js/types/generated/index.tsx', $config);

    expect($sameDirectory)->toContain('.');

    $config['generated']['types'] = 'resources/js/types/generated/types.ts';
    $nonIndex = $generatedImportSpecifiers->invoke($auditor, 'resources/js/features/vehicles/vehicle.types.ts', $config);

    expect($nonIndex)
        ->toBeArray()
        ->toContain('../../types/generated/types');
});

test('returns default list value and honors scannable file detection', function (): void {
    $auditor = new Auditor($this->app);

    $isScannableFrontendFile = new ReflectionMethod($auditor, 'isScannableFrontendFile');

    $stringList = new ReflectionMethod($auditor, 'stringList');

    expect($isScannableFrontendFile->invoke($auditor, 'resources/js/features/vehicle.ts'))
        ->toBeTrue()
        ->and($isScannableFrontendFile->invoke($auditor, 'resources/js/notes.md'))
        ->toBeFalse()
        ->and($stringList->invoke($auditor, 'bad', ['defaults']))
        ->toBe(['defaults']);

    $stripComments = new ReflectionMethod($auditor, 'stripComments');

    $stripped = $stripComments->invoke($auditor, "/* comment */\n// another\nconst value = 1;");

    expect($stripped)->not->toContain('comment')
        ->and($stripped)->toContain('const value = 1;')
        ->and(substr_count((string) $stripped, "\n"))->toBe(2);
});
