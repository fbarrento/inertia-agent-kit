<?php

declare(strict_types=1);

use InertiaAgentKit\Handoff\HandoffValidator;

describe('HandoffValidatorTest', function (): void {
it('accepts a valid completed handoff payload', function (): void {
    $result = iak_handoff_validate_payload();

    expect($result['valid'])->toBeTrue()
        ->and($result['status'])->toBe('valid')
        ->and($result['errors'])->toBe([])
        ->and($result['nextActions'])->toBe([]);
});

it('rejects a completed handoff with missing changed files', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload): array {
        unset($payload['changedFiles']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.changed_files.required');
});

it('rejects invalid changed file paths', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload): array {
        $payload['changedFiles']['page'][0]['path'] = '../outside.php';

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.changed_files.path_invalid');
});

it('rejects failed audit evidence on a completed handoff', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload): array {
        $payload['evidence']['audit']['status'] = 'failed';

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.evidence.audit_failed');
});

it('rejects failed test evidence on a completed handoff', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload): array {
        $payload['evidence']['tests']['status'] = 'failed';

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.evidence.tests_failed');
});

it('requires feedback unresolved evidence even when zero is valid', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload): array {
        unset($payload['evidence']['feedback']['unresolved']);

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.evidence.feedback_unresolved_missing');
});

it('rejects missing referenced artifacts', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload, string $basePath): array {
        unlink($basePath.'/.iak/runs/run_01/tests.json');

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.artifact.missing');
});

it('rejects artifact paths outside allowed artifact roots and changed files', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload, string $basePath): array {
        iak_handoff_write_file($basePath, 'storage/handoff.json', '{}');
        $payload['artifacts']['handoff']['path'] = 'storage/handoff.json';

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.artifact.path_not_allowed');
});

it('rejects blocking next actions on completed handoffs', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload): array {
        $payload['nextActions'] = [[
            'type' => 'fix',
            'summary' => 'Run another implementation pass.',
            'blocking' => true,
        ]];

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.next_actions.blocking')
        ->and($result['nextActions'][0]['blocking'])->toBeTrue();
});

it('rejects notes over the handoff token budget limit', function (): void {
    $result = iak_handoff_validate_payload(function (array $payload): array {
        $payload['notes'] = [str_repeat('a', 301)];

        return $payload;
    });

    expect($result['valid'])->toBeFalse()
        ->and(iak_handoff_error_codes($result))->toContain('handoff.notes.too_long');
});
});

/**
 * @param callable(array<string, mixed>, string): array<string, mixed>|null $mutate
 *
 * @return array{valid: bool, status: string, errors: list<array<string, mixed>>, nextActions: list<array<string, mixed>>}
 */
function iak_handoff_validate_payload(?callable $mutate = null): array
{
    $basePath = iak_handoff_make_base_path();

    try {
        $payload = iak_handoff_valid_payload($basePath);

        if ($mutate !== null) {
            $payload = $mutate($payload, $basePath) ?? $payload;
        }

        return (new HandoffValidator())->validate($payload, $basePath);
    } finally {
        iak_handoff_remove_directory($basePath);
    }
}

function iak_handoff_make_base_path(): string
{
    $basePath = sys_get_temp_dir().'/iak-handoff-validator-'.bin2hex(random_bytes(6));

    mkdir($basePath, 0755, true);

    return $basePath;
}

/**
 * @return array<string, mixed>
 */
function iak_handoff_valid_payload(string $basePath): array
{
    iak_handoff_write_file($basePath, 'resources/js/pages/vehicles/index.tsx', 'export default function Index() {}');
    iak_handoff_write_file($basePath, 'tests/Feature/VehicleIndexTest.php', '<?php');
    iak_handoff_write_file($basePath, '.iak/runs/run_01/audit.json', '{}');
    iak_handoff_write_file($basePath, '.iak/runs/run_01/tests.json', '{}');
    iak_handoff_write_file($basePath, '.iak/runs/run_01/screenshots/vehicles-index.png', 'png');
    iak_handoff_write_file($basePath, '.iak/runs/run_01/handoff.json', '{}');

    return [
        'schema' => 'iak.handoff.v1',
        'runId' => 'run_01',
        'task' => 'Create vehicle index page',
        'status' => 'completed',
        'summary' => 'Vehicle index page implemented and verified.',
        'changedFiles' => [
            'page' => [
                [
                    'path' => 'resources/js/pages/vehicles/index.tsx',
                    'action' => 'create',
                ],
            ],
            'test' => [
                [
                    'path' => 'tests/Feature/VehicleIndexTest.php',
                    'action' => 'create',
                ],
            ],
        ],
        'evidence' => [
            'audit' => [
                'status' => 'passed',
                'artifact' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run_01/audit.json',
                ],
            ],
            'tests' => [
                'status' => 'passed',
                'artifact' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run_01/tests.json',
                ],
            ],
            'browser' => [
                'url' => 'http://localhost.test/vehicles',
                'screenshot' => [
                    'kind' => 'screenshot',
                    'path' => '.iak/runs/run_01/screenshots/vehicles-index.png',
                ],
                'consoleErrors' => 0,
                'accessibility' => 'passed',
            ],
            'feedback' => [
                'unresolved' => 0,
            ],
        ],
        'artifacts' => [
            'handoff' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_01/handoff.json',
            ],
        ],
        'notes' => [
            'All checks passed.',
        ],
        'nextActions' => [],
        'errors' => [],
    ];
}

function iak_handoff_write_file(string $basePath, string $path, string $contents): void
{
    $absolutePath = $basePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);
    $directory = dirname($absolutePath);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($absolutePath, $contents);
}

/**
 * @param array{errors: list<array<string, mixed>>} $result
 *
 * @return list<string>
 */
function iak_handoff_error_codes(array $result): array
{
    return array_values(array_map(
        static fn (array $error): string => (string) $error['code'],
        $result['errors'],
    ));
}

function iak_handoff_remove_directory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}
