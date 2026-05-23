<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\ParseChangedFiles;

beforeEach(function (): void {
    $this->parseChangedFiles = new ParseChangedFiles;
});

test('groups valid changed file entries by role through the action', function (): void {
    $result = $this->parseChangedFiles->handle([
        'page:create:resources/js/pages/vehicles/index.tsx',
        'feature:modify:resources/js/features/vehicles/vehicle-table.tsx',
    ]);

    expect($result['errors'])->toBe([])
        ->and($result['changedFiles'])->toEqual([
            'page' => [[
                'path' => 'resources/js/pages/vehicles/index.tsx',
                'action' => 'create',
            ]],
            'feature' => [[
                'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
                'action' => 'modify',
            ]],
        ]);
});

test('returns parser errors through the action', function (): void {
    $result = $this->parseChangedFiles->handle([
        'feature:touch:resources/js/features/vehicles/vehicle-form.tsx',
        'feature:modify:../outside.ts',
    ]);

    expect($result['changedFiles'])->toBe([])
        ->and($result['errors'])->toHaveCount(2)
        ->and(array_column($result['errors'], 'code'))->toContain('changed_file.invalid_action')
        ->and(array_column($result['errors'], 'code'))->toContain('changed_file.invalid_path');
});
