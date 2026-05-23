<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use InertiaAgentKit\Console\FeedbackCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\Utils\FeedbackCommandTestHelper;
use Tests\Utils\ThrowingOutput;

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-feedback-command-'.bin2hex(random_bytes(6));

    mkdir($basePath, 0755, true);

    $this->app->setBasePath($basePath);
    $this->feedback = new FeedbackCommandTestHelper($basePath);

    config()->set('inertia-agent-kit.feedback.path', '.iak/feedback');
});

afterEach(function (): void {
    $basePath = base_path();
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-feedback-command-';

    if (str_starts_with($basePath, $prefix)) {
        $this->feedback->removeDirectory();
    }
});

test('returns an empty feedback list when the store does not exist', function (): void {
    [$exitCode, $payload] = $this->feedback->call([
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

test('lists seeded feedback with deterministic filtering and ordering', function (): void {
    $this->feedback->writeRecord([
        'id' => 'fbk_app_old',
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'human',
        'createdAt' => '2026-05-22T15:00:00Z',
        'updatedAt' => '2026-05-22T15:00:00Z',
    ]);
    $this->feedback->writeRecord([
        'id' => 'fbk_app_new',
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'human',
        'createdAt' => '2026-05-22T16:00:00Z',
        'updatedAt' => '2026-05-22T16:00:00Z',
    ]);
    $this->feedback->writeRecord([
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
    $this->feedback->writeRecord([
        'id' => 'fbk_story_agent',
        'status' => 'pending',
        'surface' => 'storybook',
        'source' => 'agent',
        'createdAt' => '2026-05-22T18:00:00Z',
        'updatedAt' => '2026-05-22T18:00:00Z',
    ]);

    [$allExitCode, $allPayload] = $this->feedback->call([
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

    [$pendingExitCode, $pendingPayload] = $this->feedback->call([
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

test('shows a complete feedback record', function (): void {
    $record = $this->feedback->writeRecord([
        'id' => 'fbk_show',
        'unknownProducerField' => [
            'kept' => true,
        ],
    ]);

    [$exitCode, $payload] = $this->feedback->call([
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

test('returns a structured error for a missing feedback record', function (): void {
    [$exitCode, $payload] = $this->feedback->call([
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

test('rejects invalid resolve evidence without mutating the record', function (): void {
    $record = $this->feedback->writeRecord([
        'id' => 'fbk_validate',
        'status' => 'pending',
    ]);

    [$missingOptionExitCode, $missingOptionPayload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_validate',
    ]);

    expect($missingOptionExitCode)->toBe(2)
        ->and($missingOptionPayload['error']['code'])->toBe('feedback.evidence_required')
        ->and($this->feedback->readRecord('fbk_validate'))->toEqual($record);

    [$missingPathExitCode, $missingPathPayload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_validate',
        '--evidence' => '.iak/runs/missing.json',
    ]);

    expect($missingPathExitCode)->toBe(2)
        ->and($missingPathPayload['error']['code'])->toBe('feedback.evidence_not_found')
        ->and($this->feedback->readRecord('fbk_validate'))->toEqual($record);

    $this->feedback->writeRaw('.iak/runs/invalid.json', '{not json');

    [$invalidJsonExitCode, $invalidJsonPayload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_validate',
        '--evidence' => '.iak/runs/invalid.json',
    ]);

    expect($invalidJsonExitCode)->toBe(2)
        ->and($invalidJsonPayload['error']['code'])->toBe('feedback.evidence_invalid_json')
        ->and($this->feedback->readRecord('fbk_validate'))->toEqual($record);

    $this->feedback->writeJson('.iak/runs/wrong-schema.json', [
        'schema' => 'iak.audit.v1',
    ]);

    [$wrongSchemaExitCode, $wrongSchemaPayload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_validate',
        '--evidence' => '.iak/runs/wrong-schema.json',
    ]);

    expect($wrongSchemaExitCode)->toBe(2)
        ->and($wrongSchemaPayload['error']['code'])->toBe('feedback.evidence_invalid_schema')
        ->and($this->feedback->readRecord('fbk_validate'))->toEqual($record);
});

test('resolves pending feedback and preserves existing record fields', function (): void {
    $record = $this->feedback->writeRecord([
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

    $this->feedback->writeJson('.iak/runs/run_01/verify.json', $evidence);

    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_resolve',
        '--evidence' => '.iak/runs/run_01/verify.json',
        '--summary' => 'Reused the shared filter bar component.',
    ]);

    $updated = $this->feedback->readRecord('fbk_resolve');

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

test('copies normalized resolution evidence and preserves unknown evidence fields', function (): void {
    $this->feedback->writeRecord([
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

    $this->feedback->writeJson('.iak/runs/run_02/handoff.json', $evidence);

    [$exitCode] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_copy',
        '--evidence' => '.iak/runs/run_02/handoff.json',
    ]);

    expect($exitCode)->toBe(0)
        ->and($this->feedback->readJson('.iak/feedback/fbk_copy/resolution/evidence.json'))->toEqual($evidence);
});

test('resolves pending feedback from a handoff artifact produced by the handoff command', function (): void {
    $this->feedback->writeRecord([
        'id' => 'fbk_handoff_artifact',
        'status' => 'pending',
    ]);

    Artisan::call('iak:handoff', [
        'action' => 'create',
        '--json' => true,
        '--run-id' => 'run_handoff_feedback',
        '--task' => 'Create vehicle index page',
        '--summary' => 'Vehicle index handoff is ready.',
        '--changed-file' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
        ],
        '--feedback-unresolved' => '0',
    ]);

    $handoffPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    $artifactPath = $handoffPayload['artifacts']['handoff']['path'];

    expect($artifactPath)->toBe('.iak/runs/run_handoff_feedback/handoff.json')
        ->and($handoffPayload['schema'])->toBe('iak.handoff.v1');

    [$exitCode, $resolvePayload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_handoff_artifact',
        '--evidence' => $artifactPath,
    ]);

    $updated = $this->feedback->readRecord('fbk_handoff_artifact');

    expect($exitCode)->toBe(0)
        ->and($resolvePayload['status'])->toBe('resolved')
        ->and($resolvePayload['resolution']['linkedEvidence'])->toBe($artifactPath)
        ->and($resolvePayload['resolution']['evidenceCopiedTo'])->toBe('.iak/feedback/fbk_handoff_artifact/resolution/evidence.json')
        ->and($updated['status'])->toBe('resolved')
        ->and($updated['resolution']['changedFiles'])->toEqual($handoffPayload['changedFiles'])
        ->and($this->feedback->readJson('.iak/feedback/fbk_handoff_artifact/resolution/evidence.json'))->toEqual($handoffPayload);
});

test('rejects path traversal evidence paths without mutating the record', function (): void {
    $record = $this->feedback->writeRecord([
        'id' => 'fbk_traversal',
        'status' => 'pending',
    ]);

    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_traversal',
        '--evidence' => '../outside.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['error']['code'])->toBe('feedback.evidence_invalid_path')
        ->and($this->feedback->readRecord('fbk_traversal'))->toEqual($record)
        ->and(file_exists(base_path('.iak/feedback/fbk_traversal/resolution/evidence.json')))->toBeFalse();
});

test('rejects unsupported feedback actions', function (): void {
    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'invalid',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['error']['code'])->toBe('feedback.invalid_action');
});

test('rejects invalid list status filters', function (): void {
    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'list',
        '--status' => 'archived',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['error']['code'])->toBe('feedback.invalid_status')
        ->and($payload['error']['details']['status'])->toBe('archived');
});

test('rejects invalid list limit values', function (): void {
    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'list',
        '--limit' => '0',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['error']['code'])->toBe('feedback.invalid_limit')
        ->and($payload['error']['details']['limit'])->toBe('0');
});

test('requires an id for show action', function (): void {
    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'show',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['error']['code'])->toBe('feedback.id_required');
});

test('rejects resolving feedback that is already resolved', function (): void {
    $this->feedback->writeRecord([
        'id' => 'fbk_closed',
        'status' => 'resolved',
    ]);
    $this->feedback->writeJson('.iak/runs/run_resolve_blocked/verify.json', [
        'schema' => 'iak.verify.v1',
    ]);

    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_closed',
        '--evidence' => '.iak/runs/run_resolve_blocked/verify.json',
    ]);

    expect($exitCode)->toBe(1)
        ->and($payload['error']['code'])->toBe('feedback.invalid_transition')
        ->and($payload['error']['details']['status'])->toBe('resolved')
        ->and($this->feedback->readRecord('fbk_closed')['status'])->toBe('resolved');
});

test('requires JSON for pretty output', function (): void {
    $exitCode = Artisan::call('iak:feedback', [
        'action' => 'list',
        '--pretty' => true,
    ]);

    expect($exitCode)->toBe(2)
        ->and(Artisan::output())->toContain('The --pretty option is only valid with JSON output.');
});

test('returns a structured internal error when command execution throws a runtime exception', function (): void {
    $command = new FeedbackCommand;
    $command->setLaravel($this->app);

    $input = new ArrayInput([
        'action' => 'list',
        '--json' => true,
    ], $command->getDefinition());
    $output = new ThrowingOutput;

    $exitCode = $command->run($input, $output);
    $payload = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(4)
        ->and($payload['error']['code'])->toBe('feedback.internal')
        ->and($payload['error']['message'])->toBe('An unexpected feedback command error occurred.')
        ->and($payload['error']['details']['exception'])->toBe('RuntimeException');
});

test('filters list output by source and excludes mismatched records', function (): void {
    $this->feedback->writeRecord([
        'id' => 'fbk_source_match',
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'human',
        'createdAt' => '2026-05-22T18:00:00Z',
    ]);
    $this->feedback->writeRecord([
        'id' => 'fbk_source_skip',
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'agent',
        'createdAt' => '2026-05-22T18:00:00Z',
    ]);

    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'list',
        '--status' => 'all',
        '--surface' => 'app',
        '--source' => 'human',
    ]);

    expect($exitCode)->toBe(0)
        ->and(array_column($payload['items'], 'id'))->toBe(['fbk_source_match']);
});

test('orders tied feedback entries by identifier', function (): void {
    $this->feedback->writeRecord([
        'id' => 'fbk_tiebreak_first',
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'human',
        'createdAt' => '2026-05-22T16:00:00Z',
    ]);
    $this->feedback->writeRecord([
        'id' => 'fbk_tiebreak_second',
        'status' => 'pending',
        'surface' => 'app',
        'source' => 'human',
        'createdAt' => '2026-05-22T16:00:00Z',
    ]);

    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'list',
        '--status' => 'all',
        '--surface' => 'app',
        '--source' => 'human',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['items'][0]['id'])->toBe('fbk_tiebreak_second')
        ->and($payload['items'][1]['id'])->toBe('fbk_tiebreak_first');
});

test('returns not found for missing feedback records on resolve', function (): void {
    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_resolve_missing',
        '--evidence' => '.iak/runs/missing.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['error']['code'])->toBe('feedback.not_found')
        ->and($payload['error']['file'])->toBe('.iak/feedback/fbk_resolve_missing/record.json');
});

test('uses fallback evidence summary when evidence contains no summary fields', function (): void {
    $this->feedback->writeRecord([
        'id' => 'fbk_empty_summary',
        'status' => 'pending',
    ]);
    $this->feedback->writeRaw('.iak/runs/run_summary/empty.json', '{"schema":"iak.verify.v1"}');

    [$exitCode, $payload] = $this->feedback->call([
        'action' => 'resolve',
        'id' => 'fbk_empty_summary',
        '--evidence' => '.iak/runs/run_summary/empty.json',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('resolved')
        ->and($payload['resolution']['summary'])->toBe('Resolved with linked evidence.')
        ->and($payload['resolution']['evidenceSummary'])->toBe('');
});

test('falls back to feedback.json_encode_failed when JSON output cannot be encoded', function (): void {
    $previousPath = config('inertia-agent-kit.feedback.path');
    $invalidPath = "\xC3\x28";

    try {
        config()->set('inertia-agent-kit.feedback.path', $invalidPath);

        $exitCode = Artisan::call('iak:feedback', [
            'action' => 'list',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(4)
            ->and($payload['schema'])->toBe('iak.error.v1')
            ->and($payload['error']['code'])->toBe('feedback.json_encode_failed');
    } finally {
        config()->set('inertia-agent-kit.feedback.path', $previousPath);
    }
});

test('returns plain text output when json is not requested', function (): void {
    $exitCode = Artisan::call('iak:feedback', [
        'action' => 'show',
        'id' => 'fbk_missing_plain',
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(2)
        ->and($output)->toContain('Feedback record fbk_missing_plain was not found.');
});

test('returns plain text output for empty list when json is not requested', function (): void {
    $exitCode = Artisan::call('iak:feedback', [
        'action' => 'list',
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Found 0 feedback record(s).');
});

test('supports pretty JSON list output', function (): void {
    $exitCode = Artisan::call('iak:feedback', [
        'action' => 'list',
        '--json' => true,
        '--pretty' => true,
    ]);
    $output = Artisan::output();
    $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(str_starts_with(trim($output), '{'))
        ->toBeTrue()
        ->and($payload['schema'])->toBe('iak.feedback.list.v1');
});

test('emits JSON when IAK_AGENT environment variable is set', function (): void {
    $previousAgentEnv = getenv('IAK_AGENT');

    try {
        putenv('IAK_AGENT=1');
        $exitCode = Artisan::call('iak:feedback', [
            'action' => 'list',
        ]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($payload['schema'])->toBe('iak.feedback.list.v1');
    } finally {
        if ($previousAgentEnv === false) {
            putenv('IAK_AGENT');
        } else {
            putenv('IAK_AGENT='.$previousAgentEnv);
        }
    }
});
