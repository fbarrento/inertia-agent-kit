<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use InertiaAgentKit\Console\VerifyCommand;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Utils\VerifyCommandTestHelper;

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-verify-command-'.bin2hex(random_bytes(6));

    mkdir($basePath, 0755, true);
    $this->app->setBasePath($basePath);
    config()->set('inertia-agent-kit.feedback.path', '.iak/feedback');
    $this->verifyHelper = new VerifyCommandTestHelper($basePath);
});

afterEach(function (): void {
    $basePath = base_path();
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-verify-command-';

    if (isset($this->verifyHelper) && str_starts_with($basePath, $prefix)) {
        $this->verifyHelper->removeDirectory();
    }
});

test('passes with clean audit evidence and no feedback', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_clean',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.verify.v1')
        ->and($payload['event'])->toBe('iak.verify.completed')
        ->and($payload['version'])->toBe(1)
        ->and($payload['command'])->toBe('iak:verify')
        ->and($payload['runId'])->toBe('run_clean')
        ->and($payload['status'])->toBe('passed')
        ->and($payload['mode'])->toBe('first-port')
        ->and($payload['checks'][0]['id'])->toBe('audit')
        ->and($payload['checks'][0]['status'])->toBe('passed')
        ->and($payload['checks'][0]['artifact']['path'])->toBe('.iak/runs/run_clean/audit.json')
        ->and($payload['checks'][0]['artifact']['schema'])->toBe('iak.audit.v1')
        ->and($payload['checks'][1]['id'])->toBe('feedback')
        ->and($payload['checks'][1]['status'])->toBe('passed')
        ->and($payload['checks'][1]['related'])->toBe(0)
        ->and($payload['checks'][1]['unresolved'])->toBe(0)
        ->and($payload['checks'][1]['invalidResolved'])->toBe(0)
        ->and($payload['checks'][2]['id'])->toBe('browser')
        ->and($payload['checks'][2]['status'])->toBe('skipped')
        ->and($payload['checks'][2]['reason'])->toBe('first_port_no_browser_execution')
        ->and($payload['checks'][3]['id'])->toBe('storybook')
        ->and($payload['checks'][3]['status'])->toBe('skipped')
        ->and($payload['checks'][3]['reason'])->toBe('first_port_no_storybook_execution')
        ->and($payload['evidence']['audit']['status'])->toBe('passed')
        ->and($payload['evidence']['audit']['runId'])->toBe('run_clean')
        ->and($payload['evidence']['audit']['violations'])->toBe(0)
        ->and($payload['evidence']['feedback']['related'])->toBe(0)
        ->and($payload['evidence']['feedback']['unresolved'])->toBe(0)
        ->and($payload['evidence']['screenshots']['status'])->toBe('placeholder')
        ->and($payload['evidence']['screenshots']['artifact']['path'])->toBe('.iak/runs/run_clean/screenshots/metadata.json')
        ->and($payload['errors'])->toBe([])
        ->and($this->verifyHelper->readJson('.iak/runs/run_clean/verify.json'))->toEqual($payload)
        ->and($this->verifyHelper->readJson('.iak/runs/run_clean/screenshots/metadata.json'))->toMatchArray([
            'schema' => 'iak.verify.screenshots.v1',
            'runId' => 'run_clean',
            'status' => 'placeholder',
        ]);
});

test('fails when the audit reports a violation', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->write('resources/js/features/vehicles/vehicle-table.tsx', <<<'TSX'
export function VehicleTable() {
    return <section className="p-[34px] bg-blue-500 text-ds-body">Vehicles</section>;
}
TSX);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_audit_failed',
    ]);

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('failed')
        ->and($payload['evidence']['audit']['status'])->toBe('failed')
        ->and($payload['evidence']['audit']['artifact']['path'])->toBe('.iak/runs/run_audit_failed/audit.json')
        ->and($payload['checks'][0]['totals']['errors'])->toBeGreaterThan(0)
        ->and($this->verifyHelper->readJson('.iak/runs/run_audit_failed/audit.json')['status'])->toBe('failed');
});

test('fails when related feedback is pending', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeFeedback([
        'id' => 'fbk_pending',
        'status' => 'pending',
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_pending_feedback',
    ]);

    $unresolved = $this->verifyHelper->readJson('.iak/runs/run_pending_feedback/feedback/unresolved.json');

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('failed')
        ->and($payload['evidence']['feedback'])->toMatchArray([
            'related' => 1,
            'unresolved' => 1,
            'invalidResolved' => 0,
            'ids' => ['fbk_pending'],
        ])
        ->and($payload['errors'][0]['code'])->toBe('feedback.unresolved')
        ->and($unresolved['count'])->toBe(1)
        ->and($unresolved['items'][0]['id'])->toBe('fbk_pending')
        ->and($unresolved['items'][0]['status'])->toBe('pending');
});

test('passes while preparing evidence for the target feedback id', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeFeedback([
        'id' => 'fbk_target',
        'status' => 'in_progress',
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_feedback_target',
        '--feedback' => 'fbk_target',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('passed')
        ->and($payload['scope']['feedback'])->toBe(['fbk_target'])
        ->and($payload['evidence']['feedback'])->toMatchArray([
            'related' => 1,
            'unresolved' => 0,
            'target' => 'fbk_target',
            'ids' => [],
            'excludedIds' => ['fbk_target'],
        ])
        ->and($payload['checks'][1]['artifact']['path'])->toBe('.iak/runs/run_feedback_target/feedback/unresolved.json')
        ->and($payload['commandsRun'][0]['cmd'])->toContain('--feedback=fbk_target');
});

test('blocks when target feedback id is not found', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_feedback_not_found',
        '--feedback' => 'missing-feedback-id',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('feedback.not_found')
        ->and($payload['checks'][1]['status'])->toBe('blocked')
        ->and($payload['evidence']['feedback']['target'])->toBe('missing-feedback-id');
});

test('blocks stale supplied audit artifacts', function (): void {
    $this->verifyHelper->cleanFixture();
    $audit = $this->verifyHelper->auditArtifact([
        'runId' => 'run_old_audit',
        'artifacts' => [
            'audit' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_old_audit/audit.json',
                'schema' => 'iak.audit.v1',
            ],
        ],
        'meta' => [
            'configHash' => 'sha256:stale',
        ],
    ]);

    $this->verifyHelper->writeJson('.iak/runs/run_old_audit/audit.json', $audit);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_verify_stale',
        '--audit' => '.iak/runs/run_old_audit/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.stale_artifact')
        ->and($payload['artifacts']['audit']['path'])->toBe('.iak/runs/run_old_audit/audit.json')
        ->and($this->verifyHelper->readJson('.iak/runs/run_verify_stale/verify.json')['status'])->toBe('blocked');
});

test('accepts a valid supplied audit artifact', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeJson('.iak/runs/run_supplied_audit/audit.json', [
        'schema' => 'iak.audit.v1',
        'event' => 'iak.audit.completed',
        'version' => 1,
        'command' => 'iak:audit',
        'runId' => 'run_supplied_audit',
        'status' => 'passed',
        'summary' => 'Audit passed: no IAK convention violations found.',
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
                'path' => '.iak/runs/run_supplied_audit/audit.json',
                'schema' => 'iak.audit.v1',
            ],
        ],
        'nextActions' => [],
        'errors' => [],
        'meta' => [
            'configHash' => $this->verifyHelper->configHash(),
        ],
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_supplied_audit',
        '--audit' => '.iak/runs/run_supplied_audit/audit.json',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('passed')
        ->and($payload['evidence']['audit']['source'])->toBe('supplied')
        ->and($payload['checks'][0]['artifact']['path'])->toBe('.iak/runs/run_supplied_audit/audit.json');
});

test('blocks when supplied audit artifact is missing', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_supplied_audit_missing',
        '--audit' => '.iak/runs/run_missing_supplied_audit/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.artifact_not_found');
});

test('blocks when supplied audit artifact has invalid status', function (): void {
    $this->verifyHelper->cleanFixture();
    $audit = $this->verifyHelper->auditArtifact([
        'runId' => 'run_bad_audit_status',
        'status' => 'invalid',
        'artifacts' => [
            'audit' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_bad_audit_status/audit.json',
                'schema' => 'iak.audit.v1',
            ],
        ],
    ]);
    $this->verifyHelper->writeJson('.iak/runs/run_bad_audit_status/audit.json', $audit);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_verify_bad_audit',
        '--audit' => '.iak/runs/run_bad_audit_status/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.schema_invalid');
});

test('blocks when supplied audit evidence is not a JSON object', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->write('.iak/runs/run_audit_list_payload/audit.json', '[]');

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_audit_list_payload',
        '--audit' => '.iak/runs/run_audit_list_payload/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.schema_invalid');
});

test('blocks when supplied audit artifact reference path is invalid', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeJson('.iak/runs/run_audit_reference_path/audit.json', [
        'schema' => 'iak.audit.v1',
        'event' => 'iak.audit.completed',
        'version' => 1,
        'command' => 'iak:audit',
        'runId' => 'run_audit_reference_path',
        'status' => 'passed',
        'summary' => 'Invalid reference path',
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
                'path' => '../.iak/runs/run_audit_reference_path/audit.json',
                'schema' => 'iak.audit.v1',
            ],
        ],
        'nextActions' => [],
        'errors' => [],
        'meta' => ['configHash' => $this->verifyHelper->configHash()],
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_audit_reference_path',
        '--audit' => '.iak/runs/run_audit_reference_path/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.schema_invalid');
});

test('blocks when supplied audit artifact reference file is missing', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeJson('.iak/runs/run_audit_reference_missing/audit.json', [
        'schema' => 'iak.audit.v1',
        'event' => 'iak.audit.completed',
        'version' => 1,
        'command' => 'iak:audit',
        'runId' => 'run_audit_reference_missing',
        'status' => 'passed',
        'summary' => 'Missing reference',
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
                'path' => '.iak/runs/missing-run-audit/audit.json',
                'schema' => 'iak.audit.v1',
            ],
        ],
        'nextActions' => [],
        'errors' => [],
        'meta' => ['configHash' => $this->verifyHelper->configHash()],
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_audit_reference_missing',
        '--audit' => '.iak/runs/run_audit_reference_missing/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.stale_artifact');
});

test('blocks when supplied audit artifact is outside .iak/runs', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeJson('.iak/verify-audit.json', $this->verifyHelper->auditArtifact());

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_audit_outside_runs',
        '--audit' => '.iak/verify-audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.invalid_path');
});

test('blocks when supplied audit artifact is invalid JSON', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->write('.iak/runs/run_invalid_json/audit.json', "{bad\n");

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_audit_invalid_json',
        '--audit' => '.iak/runs/run_invalid_json/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.schema_invalid');
});

test('blocks when supplied audit artifact does not match schema', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeJson('.iak/runs/run_schema_invalid/audit.json', [
        'schema' => 'iak.audit.v1',
        'status' => 'passed',
        'version' => 1,
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_audit_schema_invalid',
        '--audit' => '.iak/runs/run_schema_invalid/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.schema_invalid');
});

test('blocks when custom config file is invalid', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->write('config/verify-invalid-config.php', <<<'PHP'
<?php
return [
    'paths' => [
        'root' => null,
    ],
    'generated' => [
        'type_alias' => null,
    ],
    'audit' => null,
    'forbidden_folders' => 'invalid',
];
PHP);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_invalid_config',
        '--config' => 'config/verify-invalid-config.php',
    ]);

    $codes = array_column($payload['errors'] ?? [], 'code');

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($codes)->toContain('iak.config.paths.root_invalid')
        ->and($codes)->toContain('iak.config.generated.type_alias_invalid')
        ->and($codes)->toContain('iak.config.audit_invalid')
        ->and($codes)->toContain('iak.config.forbidden_folders_invalid');
});

test('blocks when custom config file is not readable', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_missing_config',
        '--config' => '.iak/missing-config.php',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.verify.internal');
});

test('blocks when custom config file does not return an array', function (): void {
    $this->verifyHelper->cleanFixture();

    $this->verifyHelper->write('config/verify-non-array-config.php', <<<'PHP'
<?php
return 'invalid-config';
PHP);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_config_not_array',
        '--config' => 'config/verify-non-array-config.php',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.verify.internal');
});

test('falls back to default runs path when configured runs path is empty', function (): void {
    $this->verifyHelper->cleanFixture();

    $this->verifyHelper->write('config/verify-empty-runs-config.php', <<<'PHP'
<?php
return [
    'paths' => [
        'root' => 'resources/js',
        'features' => 'resources/js/features',
        'components_ui' => 'resources/js/components/ui',
        'components_app' => 'resources/js/components/app',
        'runs' => '',
    ],
];
PHP);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_config_runs_empty',
        '--config' => 'config/verify-empty-runs-config.php',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.config.paths.runs_invalid')
        ->and($payload['artifacts']['verify']['path'])->toBe('.iak/runs/run_config_runs_empty/verify.json');
});

test('uses default adapter in metadata when config adapter is empty', function (): void {
    $this->verifyHelper->cleanFixture();

    $this->verifyHelper->write('config/verify-empty-adapter-config.php', <<<'PHP'
<?php
return [
    'adapter' => '',
];
PHP);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_config_empty_adapter',
        '--config' => 'config/verify-empty-adapter-config.php',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('passed')
        ->and($payload['meta']['adapter'])->toBe('laravel-inertia-react');
});

test('fails validation for an invalid run-id', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => '../run/invalid',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.usage.invalid_run_id')
        ->and($payload['runId'])->toBe('../run/invalid');
});

test('auto-generates run-id when option is omitted', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $payload] = $this->verifyHelper->call();

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('passed')
        ->and(str_starts_with((string) $payload['runId'], 'run_'))->toBeTrue();
});

test('reports pretty JSON when requested', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $output] = $this->verifyHelper->callRaw([
        '--run-id' => 'run_pretty_json',
        '--pretty' => true,
    ]);

    $payload = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['runId'])->toBe('run_pretty_json')
        ->and($output)->toContain(PHP_EOL.'    "schema": "iak.verify.v1"')
        ->and($output)->toContain(PHP_EOL.'    "runId": "run_pretty_json"');
});

test('writes JSON when IAK_AGENT env is set', function (): void {
    $previousAgent = getenv('IAK_AGENT');
    putenv('IAK_AGENT=1');

    try {
        $this->verifyHelper->cleanFixture();

        [$exitCode, $output] = $this->verifyHelper->callRaw([
            '--run-id' => 'run_agent_env',
        ], false);

        $payload = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(0)
            ->and($payload['status'])->toBe('passed')
            ->and($payload['runId'])->toBe('run_agent_env');
    } finally {
        if ($previousAgent === false) {
            putenv('IAK_AGENT=');
        } else {
            putenv("IAK_AGENT={$previousAgent}");
        }
    }
});

test('falls back to blocked JSON payload when JSON output fails once', function (): void {
    $this->verifyHelper->cleanFixture();

    $output = new class extends BufferedOutput
    {
        private int $writes = 0;

        protected function doWrite(string $message, bool $newline): void
        {
            $this->writes++;

            if ($this->writes === 1) {
                throw new JsonException('first-write-json-failure');
            }

            parent::doWrite($message, $newline);
        }
    };

    $exitCode = Artisan::call('iak:verify', [
        '--run-id' => 'run_verify_json_failure',
        '--json' => true,
    ], $output);

    $payload = json_decode((string) $output->fetch(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('iak.json.failed');
});

test('blocks from FeedbackException when feedback records are malformed', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->write('.iak/feedback/fbk_bad/record.json', '[{"id":123}]');

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_feedback_feedback_exception',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('feedback.record_invalid_json');
});

test('rejects non-normalized supplied audit paths', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_supplied_audit_non_normalized',
        '--audit' => '../.iak/runs/run_supplied_audit/audit.json',
    ]);

    expect($exitCode)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('audit.invalid_path');
});

test('writes plain text output when json flag is disabled', function (): void {
    $this->verifyHelper->cleanFixture();

    [$exitCode, $output] = $this->verifyHelper->callRaw([
        '--run-id' => 'run_text_output',
    ], false);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Verify passed: audit passed and no related feedback is unresolved.');
});

test('keeps closed feedback with invalid resolution blocked', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeFeedback([
        'id' => 'fbk_resolved_invalid',
        'status' => 'resolved',
        'resolution' => null,
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_feedback_resolution',
    ]);

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('failed')
        ->and($payload['errors'][0]['code'])->toBe('feedback.unresolved')
        ->and($payload['evidence']['feedback']['unresolved'])->toBe(1)
        ->and($payload['evidence']['feedback']['invalidResolved'])->toBe(1);
});

test('keeps duplicate resolution schemas blocked when schema is invalid', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeFeedback([
        'id' => 'fbk_invalid_resolution_schema',
        'status' => 'resolved',
        'resolution' => [
            'schema' => 'invalid.schema',
            'status' => 'resolved',
        ],
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_feedback_invalid_resolution_schema',
    ]);

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('failed')
        ->and($payload['checks'][1]['status'])->toBe('failed')
        ->and($payload['evidence']['feedback']['invalidResolved'])->toBe(1);
});

test('returns null when path is already absolute', function (): void {
    $command = app(VerifyCommand::class);
    $method = new ReflectionMethod($command, 'normalizeProjectRelativePath');

    expect($method->invoke($command, '/tmp/run-id'))->toBeNull();
});

test('skips empty path segments when normalizing path', function (): void {
    $command = app(VerifyCommand::class);
    $method = new ReflectionMethod($command, 'normalizeProjectRelativePath');

    expect($method->invoke($command, 'runs//run_clean//verify.json'))->toBe('runs/run_clean/verify.json');
});

test('uses totals errors as audit violations when violations are missing', function (): void {
    $command = app(VerifyCommand::class);
    $method = new ReflectionMethod($command, 'auditEvidenceFromPayload');

    /** @var array<string, mixed> $result */
    $result = $method->invoke($command, [
        'schema' => 'iak.audit.v1',
        'status' => 'passed',
        'runId' => 'run_missing_violations',
        'totals' => [
            'errors' => 3,
            'warnings' => 0,
        ],
    ], '.iak/runs/run_missing_violations/audit.json', 'supplied', null, 99, []);

    expect($result['violations'])->toBe(3)
        ->and($result['totals']['violations'])->toBe(3);
});

test('allows duplicate feedback with valid duplicate resolution', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeFeedback([
        'id' => 'fbk_original',
        'status' => 'resolved',
        'resolution' => [
            'schema' => 'iak.feedback.resolution.v1',
            'status' => 'resolved',
        ],
    ]);
    $this->verifyHelper->writeFeedback([
        'id' => 'fbk_duplicate',
        'status' => 'duplicate',
        'duplicateOf' => 'fbk_original',
        'resolution' => [
            'schema' => 'iak.feedback.resolution.v1',
            'status' => 'duplicate',
            'duplicateOf' => 'fbk_original',
        ],
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_feedback_duplicate',
    ]);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('passed')
        ->and($payload['evidence']['feedback']['unresolved'])->toBe(0)
        ->and($payload['evidence']['feedback']['invalidResolved'])->toBe(0);
});

test('treats feedback with unsupported resolved status as invalid', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeFeedback([
        'id' => 'fbk_invalid_resolution_status',
        'status' => 'resolved',
        'resolution' => [
            'schema' => 'iak.feedback.resolution.v1',
            'status' => 'in_progress',
        ],
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_feedback_invalid_resolution_status',
    ]);

    expect($exitCode)->toBe(1)
        ->and($payload['status'])->toBe('failed')
        ->and($payload['checks'][1]['status'])->toBe('failed')
        ->and($payload['evidence']['feedback']['invalidResolved'])->toBe(1);
});

test('writes the verify artifact and feedback artifacts for failed feedback runs', function (): void {
    $this->verifyHelper->cleanFixture();
    $this->verifyHelper->writeFeedback([
        'id' => 'fbk_write_artifacts',
        'status' => 'pending',
    ]);

    [$exitCode, $payload] = $this->verifyHelper->call([
        '--run-id' => 'run_write_artifacts',
    ]);

    expect($exitCode)->toBe(1)
        ->and($this->verifyHelper->readJson('.iak/runs/run_write_artifacts/verify.json'))->toEqual($payload)
        ->and($this->verifyHelper->readJson('.iak/runs/run_write_artifacts/feedback/related.json'))->toMatchArray([
            'count' => 1,
        ])
        ->and($this->verifyHelper->readJson('.iak/runs/run_write_artifacts/feedback/unresolved.json'))->toMatchArray([
            'count' => 1,
        ]);
});
