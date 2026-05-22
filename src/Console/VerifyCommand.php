<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InertiaAgentKit\Audit\Auditor;
use InertiaAgentKit\Feedback\FeedbackException;
use InertiaAgentKit\Feedback\FeedbackStore;
use InertiaAgentKit\Support\Files;
use InertiaAgentKit\Support\ProjectPaths;
use JsonException;
use RuntimeException;
use Throwable;

final class VerifyCommand extends Command
{
    private const OPEN_FEEDBACK_STATUSES = ['pending', 'in_progress'];

    private const CLOSED_FEEDBACK_STATUSES = ['resolved', 'wont_fix', 'duplicate'];

    protected $signature = 'iak:verify
        {--json : Emit one machine-readable JSON response}
        {--pretty : Pretty-print JSON when JSON output is active}
        {--run-id= : Optional verify run id for deterministic tests}
        {--config= : Optional config path, default config/inertia-agent-kit.php}
        {--audit= : Optional project-relative path to an existing iak.audit.v1 artifact}
        {--feedback= : Feedback id to prepare resolution evidence for}';

    protected $description = 'Verify an Inertia Agent Kit implementation run.';

    public function handle(): int
    {
        $command = $this->getName() ?? 'iak:verify';
        $paths = new ProjectPaths($this->laravel);
        $files = new Files($paths);
        $runId = $this->resolveRunId();
        $config = $this->defaultConfig();
        $artifactPath = $this->runArtifactPath($paths, $runId, ['verify.json'], $config);
        $startedAt = microtime(true);
        $createdAt = gmdate('c');

        if (! $this->isValidRunId($runId)) {
            $artifactPath = $this->runArtifactPath($paths, 'run_invalid', ['verify.json'], $config);
            $payload = $this->payload(
                command: $command,
                runId: $runId,
                status: 'blocked',
                summary: 'Verify blocked: run id may contain only letters, numbers, dots, underscores, and dashes.',
                config: $config,
                artifactPath: $artifactPath,
                audit: $this->emptyAuditEvidence($runId, $paths, $config),
                feedback: $this->emptyFeedbackEvidence($runId, $paths, $config, null),
                screenshots: $this->placeholderScreenshots($runId, $paths, $config),
                nextActions: [],
                errors: [$this->errorPayload('iak.usage.invalid_run_id', 'Run id may contain only letters, numbers, dots, underscores, and dashes.', ['runId' => $runId])],
                createdAt: $createdAt,
                finishedAt: gmdate('c'),
                durationMs: $this->durationMs($startedAt),
                exitCode: self::INVALID,
            );

            $this->tryWriteJson($files, $artifactPath, $payload);

            return $this->emitPayload($payload, self::INVALID);
        }

        try {
            $config = $this->verifyConfig($paths);
            $configErrors = $this->validateConfig($config);
            $artifactPath = $this->runArtifactPath($paths, $runId, ['verify.json'], $config);

            if ($configErrors !== []) {
                $payload = $this->payload(
                    command: $command,
                    runId: $runId,
                    status: 'blocked',
                    summary: 'Verify blocked: config is invalid.',
                    config: $config,
                    artifactPath: $artifactPath,
                    audit: $this->emptyAuditEvidence($runId, $paths, $config),
                    feedback: $this->emptyFeedbackEvidence($runId, $paths, $config, $this->feedbackTarget()),
                    screenshots: $this->placeholderScreenshots($runId, $paths, $config),
                    nextActions: [],
                    errors: $configErrors,
                    createdAt: $createdAt,
                    finishedAt: gmdate('c'),
                    durationMs: $this->durationMs($startedAt),
                    exitCode: self::INVALID,
                );

                $this->tryWriteJson($files, $artifactPath, $payload);

                return $this->emitPayload($payload, self::INVALID);
            }

            $audit = $this->auditEvidence($paths, $files, $runId, $config);
            $feedback = $this->feedbackEvidence($paths, $files, $runId, $config, $this->feedbackTarget());
            $screenshots = $this->placeholderScreenshots($runId, $paths, $config);
            $this->tryWriteJson($files, $screenshots['artifact']['path'], $screenshots['metadata']);

            [$status, $exitCode] = $this->statusAndExitCode($audit, $feedback);
            $errors = [
                ...$audit['errors'],
                ...$feedback['errors'],
            ];
            $nextActions = [
                ...$audit['nextActions'],
                ...$feedback['nextActions'],
            ];
            $payload = $this->payload(
                command: $command,
                runId: $runId,
                status: $status,
                summary: $this->summary($status, $audit, $feedback),
                config: $config,
                artifactPath: $artifactPath,
                audit: $audit,
                feedback: $feedback,
                screenshots: $screenshots,
                nextActions: $nextActions,
                errors: $errors,
                createdAt: $createdAt,
                finishedAt: gmdate('c'),
                durationMs: $this->durationMs($startedAt),
                exitCode: $exitCode,
            );

            $this->tryWriteJson($files, $artifactPath, $payload);

            return $this->emitPayload($payload, $exitCode);
        } catch (JsonException $exception) {
            return $this->blockedFromException($command, $paths, $files, $runId, $config, $artifactPath, $startedAt, $createdAt, 'iak.json.failed', $exception->getMessage());
        } catch (FeedbackException $exception) {
            return $this->blockedFromException($command, $paths, $files, $runId, $config, $artifactPath, $startedAt, $createdAt, $exception->errorCode(), $exception->getMessage(), $exception->details());
        } catch (Throwable $exception) {
            return $this->blockedFromException($command, $paths, $files, $runId, $config, $artifactPath, $startedAt, $createdAt, 'iak.verify.internal', $exception->getMessage(), [
                'exception' => $exception::class,
            ]);
        }
    }

    private function blockedFromException(
        string $command,
        ProjectPaths $paths,
        Files $files,
        string $runId,
        array $config,
        string $artifactPath,
        float $startedAt,
        string $createdAt,
        string $code,
        string $message,
        array $context = [],
    ): int {
        $payload = $this->payload(
            command: $command,
            runId: $runId,
            status: 'blocked',
            summary: 'Verify blocked: '.$message,
            config: $config,
            artifactPath: $artifactPath,
            audit: $this->emptyAuditEvidence($runId, $paths, $config),
            feedback: $this->emptyFeedbackEvidence($runId, $paths, $config, $this->feedbackTarget()),
            screenshots: $this->placeholderScreenshots($runId, $paths, $config),
            nextActions: [],
            errors: [$this->errorPayload($code, $message, $context)],
            createdAt: $createdAt,
            finishedAt: gmdate('c'),
            durationMs: $this->durationMs($startedAt),
            exitCode: self::INVALID,
        );

        $this->tryWriteJson($files, $artifactPath, $payload);

        return $this->emitPayload($payload, self::INVALID);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function auditEvidence(ProjectPaths $paths, Files $files, string $runId, array $config): array
    {
        $auditPath = $this->nullableStringOption('audit');

        if ($auditPath !== null) {
            return $this->suppliedAuditEvidence($paths, $auditPath, $config);
        }

        return $this->runAuditEvidence($paths, $files, $runId, $config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function runAuditEvidence(ProjectPaths $paths, Files $files, string $runId, array $config): array
    {
        $startedAt = microtime(true);
        $artifactPath = $this->runArtifactPath($paths, $runId, ['audit.json'], $config);
        $result = (new Auditor($this->laravel))->run($config);
        $payload = $this->auditPayload($runId, $artifactPath, $config, $result);

        $this->tryWriteJson($files, $artifactPath, $payload);

        return $this->auditEvidenceFromPayload(
            payload: $payload,
            artifactPath: $artifactPath,
            source: 'generated',
            command: $this->auditCommandString($runId),
            durationMs: $this->durationMs($startedAt),
            errors: [],
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function suppliedAuditEvidence(ProjectPaths $paths, string $path, array $config): array
    {
        $startedAt = microtime(true);
        $normalized = $this->normalizeProjectRelativePath($path);
        $artifact = [
            'kind' => 'json',
            'path' => $normalized,
            'schema' => (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1'),
        ];

        if ($normalized === null || ! str_starts_with($normalized, '.iak/runs/')) {
            return $this->blockedAuditEvidence($artifact, 'audit.invalid_path', 'Audit artifact must be a project-relative path under .iak/runs.', $startedAt);
        }

        $absolute = $paths->basePath($normalized);

        if (! is_file($absolute)) {
            return $this->blockedAuditEvidence($artifact, 'audit.artifact_not_found', "Audit artifact [{$normalized}] was not found.", $startedAt);
        }

        try {
            $decoded = json_decode((string) file_get_contents($absolute), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return $this->blockedAuditEvidence($artifact, 'audit.schema_invalid', "Audit artifact [{$normalized}] is not valid JSON.", $startedAt, [
                'jsonError' => $exception->getMessage(),
            ]);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return $this->blockedAuditEvidence($artifact, 'audit.schema_invalid', "Audit artifact [{$normalized}] must contain a JSON object.", $startedAt);
        }

        $schemaErrors = $this->auditSchemaErrors($decoded, $config);

        if ($schemaErrors !== []) {
            return $this->blockedAuditEvidence($artifact, 'audit.schema_invalid', 'Audit artifact does not match iak.audit.v1.', $startedAt, [
                'errors' => $schemaErrors,
            ]);
        }

        $referencedPath = (string) ($decoded['artifacts']['audit']['path'] ?? '');
        $referencedPath = $this->normalizeProjectRelativePath($referencedPath);

        if ($referencedPath === null || ! str_starts_with($referencedPath, '.iak/runs/')) {
            return $this->blockedAuditEvidence($artifact, 'audit.schema_invalid', 'Audit artifact reference must be project-relative under .iak/runs.', $startedAt);
        }

        if (! is_file($paths->basePath($referencedPath))) {
            return $this->blockedAuditEvidence($artifact, 'audit.stale_artifact', "Audit artifact reference [{$referencedPath}] is missing.", $startedAt);
        }

        $configHash = (string) ($decoded['meta']['configHash'] ?? '');

        if ($configHash !== $this->configHash($config)) {
            return $this->blockedAuditEvidence($artifact, 'audit.stale_artifact', 'Audit artifact was created with a different config hash.', $startedAt, [
                'expectedConfigHash' => $this->configHash($config),
                'actualConfigHash' => $configHash,
            ]);
        }

        return $this->auditEvidenceFromPayload(
            payload: $decoded,
            artifactPath: $referencedPath,
            source: 'supplied',
            command: null,
            durationMs: $this->durationMs($startedAt),
            errors: [],
        );
    }

    /**
     * @param array<string, mixed> $artifact
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function blockedAuditEvidence(array $artifact, string $code, string $message, float $startedAt, array $context = []): array
    {
        return [
            'source' => 'supplied',
            'status' => 'blocked',
            'runId' => null,
            'configHash' => null,
            'violations' => 0,
            'artifact' => $artifact,
            'command' => null,
            'durationMs' => $this->durationMs($startedAt),
            'totals' => [
                'errors' => 0,
                'warnings' => 0,
                'violations' => 0,
            ],
            'payload' => null,
            'nextActions' => [[
                'type' => 'refresh_audit',
                'summary' => 'Refresh audit evidence by omitting --audit.',
            ]],
            'errors' => [$this->errorPayload($code, $message, $context)],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array<string, mixed>> $errors
     *
     * @return array<string, mixed>
     */
    private function auditEvidenceFromPayload(array $payload, string $artifactPath, string $source, ?string $command, int $durationMs, array $errors): array
    {
        $violations = isset($payload['violations']) && is_array($payload['violations'])
            ? count($payload['violations'])
            : (int) ($payload['totals']['errors'] ?? 0);

        $totals = isset($payload['totals']) && is_array($payload['totals']) ? $payload['totals'] : [];

        return [
            'source' => $source,
            'status' => (string) ($payload['status'] ?? 'blocked'),
            'runId' => isset($payload['runId']) && is_string($payload['runId']) ? $payload['runId'] : null,
            'configHash' => isset($payload['meta']['configHash']) && is_string($payload['meta']['configHash']) ? $payload['meta']['configHash'] : null,
            'violations' => $violations,
            'artifact' => [
                'kind' => 'json',
                'path' => $artifactPath,
                'schema' => (string) ($payload['schema'] ?? 'iak.audit.v1'),
            ],
            'command' => $command,
            'durationMs' => $durationMs,
            'totals' => [
                'errors' => (int) ($totals['errors'] ?? 0),
                'warnings' => (int) ($totals['warnings'] ?? 0),
                'violations' => $violations,
            ],
            'payload' => $payload,
            'nextActions' => isset($payload['nextActions']) && is_array($payload['nextActions'])
                ? array_values($payload['nextActions'])
                : [],
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $config
     *
     * @return list<array{field: string, message: string}>
     */
    private function auditSchemaErrors(array $payload, array $config): array
    {
        $errors = [];

        foreach ([
            'schema' => (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1'),
            'event' => (string) ($config['json_schemas']['audit_completed'] ?? 'iak.audit.completed'),
            'version' => 1,
        ] as $field => $expected) {
            if (($payload[$field] ?? null) !== $expected) {
                $errors[] = ['field' => $field, 'message' => "Expected [{$field}] to be [{$expected}]."];
            }
        }

        foreach (['status', 'totals', 'checks', 'violations', 'artifacts', 'meta'] as $field) {
            if (! array_key_exists($field, $payload)) {
                $errors[] = ['field' => $field, 'message' => "Missing required field [{$field}]."];
            }
        }

        if (! in_array($payload['status'] ?? null, ['passed', 'failed', 'blocked'], true)) {
            $errors[] = ['field' => 'status', 'message' => 'Audit status must be passed, failed, or blocked.'];
        }

        if (! isset($payload['totals']) || ! is_array($payload['totals']) || array_is_list($payload['totals'])) {
            $errors[] = ['field' => 'totals', 'message' => 'Audit totals must be an object.'];
        }

        foreach (['checks', 'violations'] as $field) {
            if (! isset($payload[$field]) || ! is_array($payload[$field]) || ! array_is_list($payload[$field])) {
                $errors[] = ['field' => $field, 'message' => "Audit [{$field}] must be a list."];
            }
        }

        if (! isset($payload['artifacts']['audit']['path']) || ! is_string($payload['artifacts']['audit']['path']) || $payload['artifacts']['audit']['path'] === '') {
            $errors[] = ['field' => 'artifacts.audit.path', 'message' => 'Audit artifact path is required.'];
        }

        if (! isset($payload['meta']['configHash']) || ! is_string($payload['meta']['configHash']) || $payload['meta']['configHash'] === '') {
            $errors[] = ['field' => 'meta.configHash', 'message' => 'Audit config hash is required.'];
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $config
     * @param array{
     *     status: string,
     *     totals: array<string, int>,
     *     checks: list<array<string, mixed>>,
     *     violations: list<array<string, mixed>>,
     *     nextActions: list<array<string, mixed>>
     * } $result
     *
     * @return array<string, mixed>
     */
    private function auditPayload(string $runId, string $artifactPath, array $config, array $result): array
    {
        return [
            'schema' => (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1'),
            'event' => (string) ($config['json_schemas']['audit_completed'] ?? 'iak.audit.completed'),
            'version' => 1,
            'command' => 'iak:audit',
            'runId' => $runId,
            'status' => $result['status'],
            'summary' => $result['status'] === 'passed'
                ? 'Audit passed: no IAK convention violations found.'
                : sprintf('Audit failed: %d error(s) found.', $result['totals']['errors']),
            'totals' => $result['totals'],
            'checks' => $result['checks'],
            'violations' => $result['violations'],
            'artifacts' => [
                'audit' => [
                    'kind' => 'json',
                    'path' => $artifactPath,
                    'schema' => (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1'),
                ],
            ],
            'nextActions' => $result['nextActions'],
            'errors' => [],
            'meta' => $this->meta($config),
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function feedbackEvidence(ProjectPaths $paths, Files $files, string $runId, array $config, ?string $targetId): array
    {
        $store = new FeedbackStore($paths, (string) ($config['feedback']['path'] ?? '.iak/feedback'));
        $records = $store->all();

        usort($records, static fn (array $left, array $right): int => strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? '')));

        if ($targetId !== null && $this->findFeedbackRecord($records, $targetId) === null) {
            $artifactPaths = $this->feedbackArtifactPaths($paths, $runId, $config);

            return [
                ...$this->emptyFeedbackEvidence($runId, $paths, $config, $targetId),
                'status' => 'blocked',
                'errors' => [$this->errorPayload('feedback.not_found', "Feedback record [{$targetId}] was not found.", [
                    'feedbackId' => $targetId,
                    'path' => $store->recordPath($targetId),
                ])],
                'nextActions' => [[
                    'type' => 'inspect_feedback',
                    'summary' => "Create or select an existing feedback record before preparing evidence for [{$targetId}].",
                    'feedbackId' => $targetId,
                ]],
                'artifacts' => [
                    'related' => ['kind' => 'json', 'path' => $artifactPaths['related']],
                    'unresolved' => ['kind' => 'json', 'path' => $artifactPaths['unresolved']],
                ],
            ];
        }

        $knownIds = [];

        foreach ($records as $record) {
            if (isset($record['id']) && is_string($record['id']) && $record['id'] !== '') {
                $knownIds[$record['id']] = true;
            }
        }

        $related = [];
        $blocking = [];
        $invalidResolved = [];

        foreach ($records as $record) {
            $id = $this->feedbackId($record);
            $status = isset($record['status']) && is_string($record['status']) ? $record['status'] : 'invalid';
            $item = $this->feedbackSummaryItem($record, $status);
            $related[] = $item;

            if ($targetId !== null && $id === $targetId) {
                continue;
            }

            if (in_array($status, self::OPEN_FEEDBACK_STATUSES, true)) {
                $blocking[] = $item;

                continue;
            }

            if (in_array($status, self::CLOSED_FEEDBACK_STATUSES, true) && ! $this->hasValidResolution($record, $knownIds)) {
                $invalidResolved[] = $item;
                $blocking[] = $item;
            }
        }

        $artifactPaths = $this->feedbackArtifactPaths($paths, $runId, $config);
        $relatedPayload = [
            'schema' => 'iak.verify.feedback.related.v1',
            'runId' => $runId,
            'target' => $targetId,
            'count' => count($related),
            'items' => $related,
        ];
        $unresolvedPayload = [
            'schema' => 'iak.verify.feedback.unresolved.v1',
            'runId' => $runId,
            'target' => $targetId,
            'count' => count($blocking),
            'invalidResolved' => count($invalidResolved),
            'items' => $blocking,
        ];

        $this->tryWriteJson($files, $artifactPaths['related'], $relatedPayload);
        $this->tryWriteJson($files, $artifactPaths['unresolved'], $unresolvedPayload);

        $errors = [];

        if ($blocking !== []) {
            $errors[] = $this->errorPayload('feedback.unresolved', 'Related feedback is unresolved or has invalid resolution evidence.', [
                'ids' => array_values(array_filter(array_column($blocking, 'id'), 'is_string')),
            ]);
        }

        return [
            'status' => $blocking === [] ? 'passed' : 'failed',
            'related' => count($related),
            'unresolved' => count($blocking),
            'invalidResolved' => count($invalidResolved),
            'target' => $targetId,
            'ids' => array_values(array_filter(array_column($blocking, 'id'), 'is_string')),
            'excludedIds' => $targetId === null ? [] : [$targetId],
            'counts' => [
                'total' => count($records),
                'pending' => $this->countFeedbackStatus($records, 'pending'),
                'inProgress' => $this->countFeedbackStatus($records, 'in_progress'),
                'resolved' => $this->countFeedbackStatus($records, 'resolved'),
                'wontFix' => $this->countFeedbackStatus($records, 'wont_fix'),
                'duplicate' => $this->countFeedbackStatus($records, 'duplicate'),
            ],
            'artifacts' => [
                'related' => ['kind' => 'json', 'path' => $artifactPaths['related']],
                'unresolved' => ['kind' => 'json', 'path' => $artifactPaths['unresolved']],
            ],
            'nextActions' => array_map(static fn (array $item): array => [
                'type' => 'resolve_feedback',
                'summary' => (string) ($item['message'] ?? 'Resolve related feedback.'),
                'feedbackId' => $item['id'] ?? null,
            ], $blocking),
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function feedbackId(array $record): ?string
    {
        return isset($record['id']) && is_string($record['id']) && $record['id'] !== ''
            ? $record['id']
            : null;
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function findFeedbackRecord(array $records, string $id): ?array
    {
        foreach ($records as $record) {
            if (($record['id'] ?? null) === $id) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, bool> $knownIds
     */
    private function hasValidResolution(array $record, array $knownIds): bool
    {
        $resolution = $record['resolution'] ?? null;

        if (! is_array($resolution) || array_is_list($resolution)) {
            return false;
        }

        if (($resolution['schema'] ?? null) !== 'iak.feedback.resolution.v1') {
            return false;
        }

        $resolutionStatus = isset($resolution['status']) && is_string($resolution['status']) ? $resolution['status'] : null;

        if ($resolutionStatus !== null && ! in_array($resolutionStatus, self::CLOSED_FEEDBACK_STATUSES, true)) {
            return false;
        }

        if (($record['status'] ?? null) !== 'duplicate') {
            return true;
        }

        $duplicateOf = $resolution['duplicateOf'] ?? $record['duplicateOf'] ?? null;
        $id = $this->feedbackId($record);

        return is_string($duplicateOf)
            && $duplicateOf !== ''
            && $duplicateOf !== $id
            && isset($knownIds[$duplicateOf]);
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function feedbackSummaryItem(array $record, string $status): array
    {
        return [
            'id' => $this->feedbackId($record),
            'status' => $status,
            'surface' => $record['surface'] ?? null,
            'source' => $record['source'] ?? null,
            'message' => $record['message'] ?? null,
            'target' => $record['target'] ?? (object) [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function countFeedbackStatus(array $records, string $status): int
    {
        return count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? null) === $status));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{related: string, unresolved: string}
     */
    private function feedbackArtifactPaths(ProjectPaths $paths, string $runId, array $config): array
    {
        return [
            'related' => $this->runArtifactPath($paths, $runId, ['feedback', 'related.json'], $config),
            'unresolved' => $this->runArtifactPath($paths, $runId, ['feedback', 'unresolved.json'], $config),
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function emptyAuditEvidence(string $runId, ProjectPaths $paths, array $config): array
    {
        return [
            'source' => 'none',
            'status' => 'blocked',
            'runId' => $runId,
            'configHash' => null,
            'violations' => 0,
            'artifact' => [
                'kind' => 'json',
                'path' => $this->runArtifactPath($paths, $runId, ['audit.json'], $config),
                'schema' => (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1'),
            ],
            'command' => null,
            'durationMs' => 0,
            'totals' => [
                'errors' => 0,
                'warnings' => 0,
                'violations' => 0,
            ],
            'payload' => null,
            'nextActions' => [],
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function emptyFeedbackEvidence(string $runId, ProjectPaths $paths, array $config, ?string $targetId): array
    {
        $artifactPaths = $this->feedbackArtifactPaths($paths, $runId, $config);

        return [
            'status' => 'passed',
            'related' => 0,
            'unresolved' => 0,
            'invalidResolved' => 0,
            'target' => $targetId,
            'ids' => [],
            'excludedIds' => $targetId === null ? [] : [$targetId],
            'counts' => [
                'total' => 0,
                'pending' => 0,
                'inProgress' => 0,
                'resolved' => 0,
                'wontFix' => 0,
                'duplicate' => 0,
            ],
            'artifacts' => [
                'related' => ['kind' => 'json', 'path' => $artifactPaths['related']],
                'unresolved' => ['kind' => 'json', 'path' => $artifactPaths['unresolved']],
            ],
            'nextActions' => [],
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{artifact: array<string, mixed>, metadata: array<string, mixed>, browser: array<string, mixed>, storybook: array<string, mixed>}
     */
    private function placeholderScreenshots(string $runId, ProjectPaths $paths, array $config): array
    {
        $artifact = [
            'kind' => 'json',
            'path' => $this->runArtifactPath($paths, $runId, ['screenshots', 'metadata.json'], $config),
        ];
        $screenshot = [
            'kind' => 'screenshot',
            'path' => null,
            'status' => 'placeholder',
            'capture' => 'not_run',
            'required' => false,
        ];
        $browser = [
            'status' => 'skipped',
            'executor' => null,
            'targets' => [[
                'route' => null,
                'url' => null,
                'status' => 'placeholder',
                'viewport' => [
                    'name' => 'desktop',
                    'width' => 1440,
                    'height' => 900,
                ],
                'screenshot' => $screenshot,
                'consoleErrors' => null,
                'accessibility' => 'not_run',
            ]],
        ];
        $storybook = [
            'status' => 'skipped',
            'stories' => [[
                'storyId' => null,
                'status' => 'placeholder',
                'screenshot' => $screenshot,
                'consoleErrors' => null,
                'accessibility' => 'not_run',
            ]],
        ];

        return [
            'artifact' => $artifact,
            'metadata' => [
                'schema' => 'iak.verify.screenshots.v1',
                'runId' => $runId,
                'status' => 'placeholder',
                'items' => [],
                'browser' => $browser['targets'],
                'storybook' => $storybook['stories'],
            ],
            'browser' => $browser,
            'storybook' => $storybook,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $audit
     * @param array<string, mixed> $feedback
     * @param array{artifact: array<string, mixed>, metadata: array<string, mixed>, browser: array<string, mixed>, storybook: array<string, mixed>} $screenshots
     * @param list<array<string, mixed>> $nextActions
     * @param list<array<string, mixed>> $errors
     *
     * @return array<string, mixed>
     */
    private function payload(
        string $command,
        string $runId,
        string $status,
        string $summary,
        array $config,
        string $artifactPath,
        array $audit,
        array $feedback,
        array $screenshots,
        array $nextActions,
        array $errors,
        string $createdAt,
        string $finishedAt,
        int $durationMs,
        int $exitCode,
    ): array {
        return [
            'schema' => (string) ($config['json_schemas']['verify'] ?? 'iak.verify.v1'),
            'event' => 'iak.verify.completed',
            'version' => 1,
            'command' => $command,
            'runId' => $runId,
            'status' => $status,
            'summary' => $summary,
            'mode' => 'first-port',
            'scope' => [
                'changedFiles' => [],
                'routes' => [],
                'urls' => [],
                'stories' => [],
                'resources' => [],
                'feedback' => $feedback['target'] === null ? [] : [$feedback['target']],
            ],
            'checks' => [
                [
                    'id' => 'audit',
                    'status' => $audit['status'],
                    'command' => $audit['command'],
                    'durationMs' => $audit['durationMs'],
                    'artifact' => $audit['artifact'],
                    'totals' => $audit['totals'],
                ],
                [
                    'id' => 'feedback',
                    'status' => $feedback['status'],
                    'related' => $feedback['related'],
                    'unresolved' => $feedback['unresolved'],
                    'invalidResolved' => $feedback['invalidResolved'],
                    'target' => $feedback['target'],
                    'artifact' => $feedback['artifacts']['unresolved'],
                ],
                [
                    'id' => 'browser',
                    'status' => 'skipped',
                    'reason' => 'first_port_no_browser_execution',
                    'executor' => null,
                ],
                [
                    'id' => 'storybook',
                    'status' => 'skipped',
                    'reason' => 'first_port_no_storybook_execution',
                ],
            ],
            'evidence' => [
                'audit' => [
                    'status' => $audit['status'],
                    'runId' => $audit['runId'],
                    'configHash' => $audit['configHash'],
                    'violations' => $audit['violations'],
                    'artifact' => $audit['artifact'],
                    'source' => $audit['source'],
                ],
                'feedback' => [
                    'related' => $feedback['related'],
                    'unresolved' => $feedback['unresolved'],
                    'invalidResolved' => $feedback['invalidResolved'],
                    'target' => $feedback['target'],
                    'ids' => $feedback['ids'],
                    'excludedIds' => $feedback['excludedIds'],
                    'counts' => $feedback['counts'],
                    'artifact' => $feedback['artifacts']['unresolved'],
                    'relatedArtifact' => $feedback['artifacts']['related'],
                ],
                'browser' => $screenshots['browser'],
                'storybook' => $screenshots['storybook'],
                'screenshots' => [
                    'status' => 'placeholder',
                    'items' => [],
                    'artifact' => $screenshots['artifact'],
                ],
            ],
            'changedFiles' => [],
            'commandsRun' => [[
                'cmd' => $this->verifyCommandString(),
                'exitCode' => $exitCode,
            ]],
            'artifacts' => [
                'verify' => [
                    'kind' => 'json',
                    'path' => $artifactPath,
                    'schema' => (string) ($config['json_schemas']['verify'] ?? 'iak.verify.v1'),
                ],
                'audit' => $audit['artifact'],
                'feedback' => $feedback['artifacts']['unresolved'],
                'feedbackRelated' => $feedback['artifacts']['related'],
                'screenshots' => $screenshots['artifact'],
            ],
            'nextActions' => $nextActions,
            'errors' => $errors,
            'meta' => [
                ...$this->meta($config),
                'createdAt' => $createdAt,
                'finishedAt' => $finishedAt,
                'durationMs' => $durationMs,
                'browserExecution' => 'not_implemented',
                'auditSource' => $audit['source'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $audit
     * @param array<string, mixed> $feedback
     *
     * @return array{0: string, 1: int}
     */
    private function statusAndExitCode(array $audit, array $feedback): array
    {
        if ($audit['status'] === 'blocked' || $feedback['status'] === 'blocked') {
            return ['blocked', self::INVALID];
        }

        if ($audit['status'] !== 'passed' || $feedback['status'] !== 'passed') {
            return ['failed', self::FAILURE];
        }

        return ['passed', self::SUCCESS];
    }

    /**
     * @param array<string, mixed> $audit
     * @param array<string, mixed> $feedback
     */
    private function summary(string $status, array $audit, array $feedback): string
    {
        if ($status === 'passed') {
            return 'Verify passed: audit passed and no related feedback is unresolved.';
        }

        if ($status === 'blocked') {
            return 'Verify blocked: reliable verification evidence could not be built.';
        }

        $reasons = [];

        if ($audit['status'] !== 'passed') {
            $reasons[] = sprintf('audit %s', $audit['status']);
        }

        if ($feedback['status'] !== 'passed') {
            $reasons[] = sprintf('%d feedback record(s) blocking', $feedback['unresolved']);
        }

        return 'Verify failed: '.implode('; ', $reasons).'.';
    }

    private function resolveRunId(): string
    {
        $runId = $this->nullableStringOption('run-id');

        if ($runId !== null) {
            return $runId;
        }

        return 'run_'.strtolower((string) Str::ulid());
    }

    private function feedbackTarget(): ?string
    {
        return $this->nullableStringOption('feedback');
    }

    private function isValidRunId(string $runId): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+$/', $runId) === 1
            && ! str_contains($runId, '..')
            && ! str_contains($runId, '/')
            && ! str_contains($runId, '\\');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultConfig(): array
    {
        $config = config('inertia-agent-kit');

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyConfig(ProjectPaths $paths): array
    {
        $baseConfig = $this->defaultConfig();
        $configPath = $this->nullableStringOption('config');

        if ($configPath === null) {
            return $baseConfig;
        }

        $absoluteConfigPath = $paths->absolute($configPath);

        if (! is_file($absoluteConfigPath) || ! is_readable($absoluteConfigPath)) {
            throw new RuntimeException("Config file [{$configPath}] is not readable.");
        }

        $loaded = require $absoluteConfigPath;

        if (! is_array($loaded)) {
            throw new RuntimeException("Config file [{$configPath}] must return an array.");
        }

        return array_replace_recursive($baseConfig, $loaded);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array<string, mixed>>
     */
    private function validateConfig(array $config): array
    {
        $errors = [];

        foreach ([
            'paths' => 'Config key [paths] must be an array.',
            'generated' => 'Config key [generated] must be an array.',
            'audit' => 'Config key [audit] must be an array.',
        ] as $key => $message) {
            if (! isset($config[$key]) || ! is_array($config[$key])) {
                $errors[] = $this->errorPayload("iak.config.{$key}_invalid", $message);
            }
        }

        $paths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];

        foreach (['root', 'features', 'components_ui', 'components_app', 'runs'] as $key) {
            if (! isset($paths[$key]) || ! is_string($paths[$key]) || $paths[$key] === '') {
                $errors[] = $this->errorPayload("iak.config.paths.{$key}_invalid", "Config key [paths.{$key}] must be a non-empty string.");
            }
        }

        $generated = isset($config['generated']) && is_array($config['generated']) ? $config['generated'] : [];

        foreach (['type_alias', 'types', 'routes', 'actions'] as $key) {
            if (! isset($generated[$key]) || ! is_string($generated[$key]) || $generated[$key] === '') {
                $errors[] = $this->errorPayload("iak.config.generated.{$key}_invalid", "Config key [generated.{$key}] must be a non-empty string.");
            }
        }

        if (! isset($config['forbidden_folders']) || ! is_array($config['forbidden_folders'])) {
            $errors[] = $this->errorPayload('iak.config.forbidden_folders_invalid', 'Config key [forbidden_folders] must be an array.');
        }

        return $errors;
    }

    /**
     * @param list<string> $segments
     * @param array<string, mixed> $config
     */
    private function runArtifactPath(ProjectPaths $paths, string $runId, array $segments, array $config): string
    {
        $configPaths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];
        $runsPath = isset($configPaths['runs']) && is_string($configPaths['runs']) && $configPaths['runs'] !== ''
            ? $configPaths['runs']
            : '.iak/runs';

        return $paths->relative($paths->join($runsPath, $runId, ...$segments));
    }

    private function normalizeProjectRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function tryWriteJson(Files $files, string $path, array $value): void
    {
        $files->writeJson($path, $value);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function meta(array $config): array
    {
        $adapter = isset($config['adapter']) && is_string($config['adapter']) && $config['adapter'] !== ''
            ? $config['adapter']
            : 'react';

        return [
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => '0.1.0',
            'adapter' => 'laravel-inertia-'.$adapter,
            'configHash' => $this->configHash($config),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configHash(array $config): string
    {
        return 'sha256:'.hash('sha256', json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function durationMs(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    private function auditCommandString(string $runId): string
    {
        $command = "php artisan iak:audit --json --run-id={$runId}";
        $configPath = $this->nullableStringOption('config');

        return $configPath === null ? $command : $command." --config={$configPath}";
    }

    private function verifyCommandString(): string
    {
        $parts = ['php artisan iak:verify', '--json'];

        foreach (['run-id', 'audit', 'feedback'] as $option) {
            $value = $this->nullableStringOption($option);

            if ($value !== null) {
                $parts[] = "--{$option}={$value}";
            }
        }

        return implode(' ', $parts);
    }

    private function nullableStringOption(string $option): ?string
    {
        $value = $this->option($option);

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function errorPayload(string $code, string $message, array $context = []): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'context' => $context === [] ? (object) [] : $context,
        ];
    }

    private function shouldEmitJson(): bool
    {
        if (getenv('IAK_AGENT') === '1') {
            return true;
        }

        return (bool) $this->option('json');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emitPayload(array $payload, int $status): int
    {
        if ($this->shouldEmitJson()) {
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

            if ((bool) $this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->output->writeln(json_encode($payload, $flags));

            return $status;
        }

        $this->line((string) ($payload['summary'] ?? 'Verify completed.'));

        return $status;
    }
}
