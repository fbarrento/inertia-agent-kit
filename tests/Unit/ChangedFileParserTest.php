<?php

declare(strict_types=1);

use InertiaAgentKit\Handoff\ChangedFileParser;

it('groups valid changed file entries by role', function (): void {
    $result = (new ChangedFileParser())->parse([
        'feature:modify:resources/js/features/vehicles/vehicle-table.tsx',
        'component-ui:create:resources/js/components/ui/button.tsx',
        'docs:delete:docs/inertia-agent-kit/handoff.md',
    ]);

    expect($result['errors'])->toBe([])
        ->and($result['changedFiles'])->toEqual([
            'feature' => [[
                'path' => 'resources/js/features/vehicles/vehicle-table.tsx',
                'action' => 'modify',
            ]],
            'component-ui' => [[
                'path' => 'resources/js/components/ui/button.tsx',
                'action' => 'create',
            ]],
            'docs' => [[
                'path' => 'docs/inertia-agent-kit/handoff.md',
                'action' => 'delete',
            ]],
        ]);
});

it('reports invalid roles actions and unsafe paths', function (): void {
    $result = (new ChangedFileParser())->parse([
        'unknown:modify:resources/js/features/vehicles/table.tsx',
        'feature:touch:resources/js/features/vehicles/table.tsx',
        'feature:modify:/etc/passwd',
        'feature:modify:resources/js/../secrets.ts',
        'feature:modify:.git/config',
        'feature:modify:',
    ]);

    $codes = array_column($result['errors'], 'code');
    $reasons = array_values(array_filter(array_map(
        static fn (array $error): ?string => $error['details']['reason'] ?? null,
        $result['errors']
    )));

    expect($result['changedFiles'])->toBe([])
        ->and($codes)->toContain('changed_file.invalid_role')
        ->and($codes)->toContain('changed_file.invalid_action')
        ->and($codes)->toContain('changed_file.invalid_path')
        ->and($reasons)->toContain('absolute')
        ->and($reasons)->toContain('traversal')
        ->and($reasons)->toContain('git')
        ->and($reasons)->toContain('empty');
});

it('allows colons inside the project relative path', function (): void {
    $result = (new ChangedFileParser())->parse([
        'feature:modify:resources/js/features/time:zone-picker.tsx',
    ]);

    expect($result['errors'])->toBe([])
        ->and($result['changedFiles']['feature'][0])->toEqual([
            'path' => 'resources/js/features/time:zone-picker.tsx',
            'action' => 'modify',
        ]);
});

it('rejects malformed entries before parsing role action and path', function (): void {
    $result = (new ChangedFileParser())->parse([
        'feature:modify',
    ]);

    expect($result['changedFiles'])->toBe([])
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0]['code'])->toBe('changed_file.invalid_format');
});
