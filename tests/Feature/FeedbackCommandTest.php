<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-feedback-command-'.bin2hex(random_bytes(6));

    mkdir($basePath, 0755, true);

    $this->app->setBasePath($basePath);

    config()->set('inertia-agent-kit.feedback.path', '.iak/feedback');
});

afterEach(function (): void {
    $basePath = base_path();
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-feedback-command-';

    if (str_starts_with($basePath, $prefix)) {
        removeFeedbackFixtureDirectory($basePath);
    }
});

it('returns an empty feedback list when the store does not exist', function (): void {
    [$exitCode, $payload] = callFeedbackCommand([
        'action' => 'list',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload)->toMatchArray([
            'schema' => 'iak.feedback.list.v1',
            'status' => 'passed',
            'filters' => [
                'status' => 'pending',
                'surface' => null,
                'source' => null,
                'limit' => 50,
            ],
            'counts' => [
                'total' => 0,
                'returned' => 0,
                'pending' => 0,
                'inProgress' => 0,
                'resolved' => 0,
                'wontFix' => 0,
                'duplicate' => 0,
            ],
            'items' => [],
            'artifacts' => [
                'store' => [
                    'kind' => 'json',
                    'path' => '.iak/feedback',
                ],
            ],
            'errors' => [],
        ]);
});

it('lists seeded feedback with deterministic filtering and ordering', function (): void {
    writeFeedbackRecord([
        'id' => 'fbk_app_old',
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'human',
        'createdAt' => '2026-05-22T15:00:00Z',
        'updatedAt' => '2026-05-22T15:00:00Z',
    ]);
    writeFeedbackRecord([
        'id' => 'fbk_app_new',
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'human',
        'createdAt' => '2026-05-22T16:00:00Z',
        'updatedAt' => '2026-05-22T16:00:00Z',
    ]);
    writeFeedbackRecord([
        'id' => 'fbk_app_resolved',
        'status' => 'resolved',
        'surface' => 'app',
        'source' => 'human',
        'createdAt' => '2026-05-22T17:00:00Z',
        'updatedAt' => '2026-05-22T17:00:00Z',
        'resolution' => [
            'schema' => 'iak.feedback.resolution.v1',
            'status' => 'resolved',
        ],
    ]);
    writeFeedbackRecord([
        'id' => 'fbk_story_agent',
        'status' => 'pending',
        'surface' => 'storybook',
        'source' => 'agent',
        'createdAt' => '2026-05-22T18:00:00Z',
        'updatedAt' => '2026-05-22T18:00:00Z',
    ]);

    [$allExitCode, $allPayload] = callFeedbackCommand([
        'action' => 'list',
        '--status' => 'all',
        '--surface' => 'app',
        '--source' => 'human',
    ]);

    expect($allExitCode)->toBe(0)
        ->and(array_column($allPayload['items'], 'id'))->toBe([
            'fbk_app_resolved',
            'fbk_app_new',
            'fbk_app_old',
        ])
        ->and($allPayload['counts'])->toMatchArray([
            'total' => 3,
            'returned' => 3,
            'pending' => 2,
            'resolved' => 1,
        ]);

    [$pendingExitCode, $pendingPayload] = callFeedbackCommand([
        'action' => 'list',
        '--status' => 'pending',
        '--surface' => 'app',
        '--source' => 'human',
        '--limit' => '1',
    ]);

    expect($pendingExitCode)->toBe(0)
        ->and(array_column($pendingPayload['items'], 'id'))->toBe(['fbk_app_new'])
        ->and($pendingPayload['counts'])->toMatchArray([
            'total' => 3,
            'returned' => 1,
            'pending' => 2,
            'resolved' => 1,
        ]);
});

it('shows a complete feedback record', function (): void {
    $record = writeFeedbackRecord([
        'id' => 'fbk_show',
        'unknownProducerField' => [
            'kept' => true,
        ],
    ]);

    [$exitCode, $payload] = callFeedbackCommand([
        'action' => 'show',
        'id' => 'fbk_show',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload)->toMatchArray([
            'schema' => 'iak.feedback.show.v1',
            'status' => 'passed',
            'artifacts' => [
                'record' => [
                    'kind' => 'json',
                    'path' => '.iak/feedback/fbk_show/record.json',
                ],
            ],
            'errors' => [],
        ])
        ->and($payload['record'])->toEqual($record);
});

it('returns a structured error for a missing feedback record', function (): void {
    [$exitCode, $payload] = callFeedbackCommand([
        'action' => 'show',
        'id' => 'fbk_missing',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['schema'])->toBe('iak.error.v1')
        ->and($payload['status'])->toBe('failed')
        ->and($payload['error']['code'])->toBe('feedback.not_found')
        ->and($payload['error']['file'])->toBe('.iak/feedback/fbk_missing/record.json')
        ->and($payload['error']['line'])->toBeNull();
});

it('rejects invalid resolve evidence without mutating the record', function (): void {
    $record = writeFeedbackRecord([
        'id' => 'fbk_validate',
        'status' => 'pending',
    ]);

    [$missingOptionExitCode, $missingOptionPayload] = callFeedbackCommand([
        'action' => 'resolve',
        'id' => 'fbk_validate',
    ]);

    expect($missingOptionExitCode)->toBe(2)
        ->and($missingOptionPayload['error']['code'])->toBe('feedback.evidence_required')
        ->and(readFeedbackRecord('fbk_validate'))->toEqual($record);

    [$missingPathExitCode, $missingPathPayload] = callFeedbackCommand([
        'action' => 'resolve',
        'id' => 'fbk_validate',
        '--evidence' => '.iak/runs/missing.json',
    ]);

    expect($missingPathExitCode)->toBe(2)
        ->and($missingPathPayload['error']['code'])->toBe('feedback.evidence_not_found')
        ->and(readFeedbackRecord('fbk_validate'))->toEqual($record);

    writeRawFixture('.iak/runs/invalid.json', '{not json');

    [$invalidJsonExitCode, $invalidJsonPayload] = callFeedbackCommand([
        'action' => 'resolve',
        'id' => 'fbk_validate',
        '--evidence' => '.iak/runs/invalid.json',
    ]);

    expect($invalidJsonExitCode)->toBe(2)
        ->and($invalidJsonPayload['error']['code'])->toBe('feedback.evidence_invalid_json')
        ->and(readFeedbackRecord('fbk_validate'))->toEqual($record);

    writeJsonFixture('.iak/runs/wrong-schema.json', [
        'schema' => 'iak.audit.v1',
    ]);

    [$wrongSchemaExitCode, $wrongSchemaPayload] = callFeedbackCommand([
        'action' => 'resolve',
        'id' => 'fbk_validate',
        '--evidence' => '.iak/runs/wrong-schema.json',
    ]);

    expect($wrongSchemaExitCode)->toBe(2)
        ->and($wrongSchemaPayload['error']['code'])->toBe('feedback.evidence_invalid_schema')
        ->and(readFeedbackRecord('fbk_validate'))->toEqual($record);
});

it('resolves pending feedback and preserves existing record fields', function (): void {
    $record = writeFeedbackRecord([
        'id' => 'fbk_resolve',
        'status' => 'pending',
        'customRecordField' => [
            'preserve' => true,
        ],
    ]);
    $evidence = [
        'schema' => 'iak.verify.v1',
        'summary' => 'Verify passed after reusing the shared component.',
        'changedFiles' => [
            [
                'path' => 'resources/js/features/vehicles/filter-bar.tsx',
                'role' => 'feature',
                'action' => 'modify',
            ],
        ],
        'commandsRun' => [
            [
                'cmd' => 'php artisan iak:verify --feedback=fbk_resolve --json',
                'exitCode' => 0,
            ],
        ],
        'artifacts' => [
            'screenshotAfter' => '.iak/feedback/fbk_resolve/resolution/screenshot-after.png',
        ],
    ];

    writeJsonFixture('.iak/runs/run_01/verify.json', $evidence);

    [$exitCode, $payload] = callFeedbackCommand([
        'action' => 'resolve',
        'id' => 'fbk_resolve',
        '--evidence' => '.iak/runs/run_01/verify.json',
        '--summary' => 'Reused the shared filter bar component.',
    ]);

    $updated = readFeedbackRecord('fbk_resolve');

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.feedback.resolve.v1')
        ->and($payload['status'])->toBe('resolved')
        ->and($payload['resolution']['schema'])->toBe('iak.feedback.resolution.v1')
        ->and($payload['resolution']['status'])->toBe('resolved')
        ->and($payload['resolution']['summary'])->toBe('Reused the shared filter bar component.')
        ->and($payload['resolution']['linkedEvidence'])->toBe('.iak/runs/run_01/verify.json')
        ->and($payload['resolution']['evidenceCopiedTo'])->toBe('.iak/feedback/fbk_resolve/resolution/evidence.json')
        ->and($payload['resolution']['evidenceSummary'])->toBe('Verify passed after reusing the shared component.')
        ->and($payload['artifacts']['record']['path'])->toBe('.iak/feedback/fbk_resolve/record.json')
        ->and($payload['artifacts']['evidence']['path'])->toBe('.iak/feedback/fbk_resolve/resolution/evidence.json')
        ->and($payload['errors'])->toBe([])
        ->and($updated['status'])->toBe('resolved')
        ->and($updated['resolution']['changedFiles'])->toEqual($evidence['changedFiles'])
        ->and($updated['customRecordField'])->toEqual($record['customRecordField'])
        ->and($updated['attachments'])->toEqual($record['attachments'])
        ->and($payload['record'])->toEqual($updated);
});

it('copies normalized resolution evidence and preserves unknown evidence fields', function (): void {
    writeFeedbackRecord([
        'id' => 'fbk_copy',
        'status' => 'in_progress',
    ]);
    $evidence = [
        'schema' => 'iak.handoff.v1',
        'summary' => 'Handoff evidence proves the feedback is resolved.',
        'unknownEvidenceField' => [
            'nested' => [
                'kept' => true,
            ],
        ],
    ];

    writeJsonFixture('.iak/runs/run_02/handoff.json', $evidence);

    [$exitCode] = callFeedbackCommand([
        'action' => 'resolve',
        'id' => 'fbk_copy',
        '--evidence' => '.iak/runs/run_02/handoff.json',
    ]);

    expect($exitCode)->toBe(0)
        ->and(readJsonFixture('.iak/feedback/fbk_copy/resolution/evidence.json'))->toEqual($evidence);
});

it('rejects path traversal evidence paths without mutating the record', function (): void {
    $record = writeFeedbackRecord([
        'id' => 'fbk_traversal',
        'status' => 'pending',
    ]);

    [$exitCode, $payload] = callFeedbackCommand([
        'action' => 'resolve',
        'id' => 'fbk_traversal',
        '--evidence' => '../outside.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['error']['code'])->toBe('feedback.evidence_invalid_path')
        ->and(readFeedbackRecord('fbk_traversal'))->toEqual($record)
        ->and(file_exists(base_path('.iak/feedback/fbk_traversal/resolution/evidence.json')))->toBeFalse();
});

/**
 * @param array<string, mixed> $arguments
 *
 * @return array{0: int, 1: array<string, mixed>}
 */
function callFeedbackCommand(array $arguments): array
{
    $exitCode = Artisan::call('iak:feedback', [
        ...$arguments,
        '--json' => true,
    ]);

    return [
        $exitCode,
        json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR),
    ];
}

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function writeFeedbackRecord(array $overrides = []): array
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

    writeJsonFixture(".iak/feedback/{$id}/record.json", $record);

    return $record;
}

/**
 * @return array<string, mixed>
 */
function readFeedbackRecord(string $id): array
{
    return readJsonFixture(".iak/feedback/{$id}/record.json");
}

/**
 * @param array<string, mixed> $payload
 */
function writeJsonFixture(string $relativePath, array $payload): void
{
    writeRawFixture(
        $relativePath,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
    );
}

function writeRawFixture(string $relativePath, string $contents): void
{
    $path = base_path($relativePath);
    $directory = dirname($path);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($path, $contents);
}

/**
 * @return array<string, mixed>
 */
function readJsonFixture(string $relativePath): array
{
    return json_decode((string) file_get_contents(base_path($relativePath)), true, 512, JSON_THROW_ON_ERROR);
}

function removeFeedbackFixtureDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());

            continue;
        }

        unlink($file->getPathname());
    }

    rmdir($path);
}
