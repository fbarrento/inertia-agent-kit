<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use InertiaAgentKit\Feedback\FeedbackEvidenceValidator;
use InertiaAgentKit\Feedback\FeedbackException;
use InertiaAgentKit\Feedback\FeedbackStore;
use InertiaAgentKit\Support\ArrayData;
use InertiaAgentKit\Support\ProjectPaths;
use JsonException;
use Throwable;

final class FeedbackCommand extends Command
{
    protected $signature = 'iak:feedback
        {action=list : Feedback action: list, show, resolve}
        {id? : Feedback record id}
        {--json : Emit a machine-readable JSON response}
        {--pretty : Pretty-print JSON output}
        {--status=pending : Filter list output by status}
        {--surface= : Filter list output by surface}
        {--source= : Filter list output by source}
        {--limit=50 : Maximum records returned by list}
        {--evidence= : Project-relative resolution evidence path}
        {--summary= : Resolution summary}';

    protected $description = 'Record, inspect, and resolve Inertia Agent Kit feedback.';

    public function handle(): int
    {
        $paths = new ProjectPaths($this->laravel);
        $store = new FeedbackStore($paths, $this->configString('inertia-agent-kit.feedback.path', '.iak/feedback'));
        $evidenceValidator = new FeedbackEvidenceValidator($paths);

        try {
            if ((bool) $this->option('pretty') && ! $this->shouldEmitJson()) {
                throw new FeedbackException(
                    'feedback.invalid_option',
                    'The --pretty option is only valid with JSON output.',
                    self::INVALID,
                );
            }

            return match ($this->action()) {
                'list' => $this->list($store),
                'show' => $this->show($store),
                'resolve' => $this->resolve($store, $evidenceValidator),
                default => throw new FeedbackException(
                    'feedback.invalid_action',
                    'Feedback action must be one of list, show, or resolve.',
                    self::INVALID,
                    null,
                    ['action' => $this->argument('action')],
                ),
            };
        } catch (FeedbackException $exception) {
            return $this->failWithFeedbackError($exception);
        } catch (Throwable $exception) {
            return $this->failWithFeedbackError(new FeedbackException(
                'feedback.internal',
                'An unexpected feedback command error occurred.',
                4,
                null,
                ['exception' => $exception::class, 'message' => $exception->getMessage()],
            ));
        }
    }

    private function action(): string
    {
        $action = $this->argument('action');

        return is_string($action) && $action !== '' ? strtolower($action) : 'list';
    }

    private function list(FeedbackStore $store): int
    {
        $status = $this->statusFilter();
        $surface = $this->nullableStringOption('surface');
        $source = $this->nullableStringOption('source');
        $limit = $this->limit();

        $records = array_values(array_filter($store->all(), static function (array $record) use ($surface, $source): bool {
            if ($surface !== null && ($record['surface'] ?? null) !== $surface) {
                return false;
            }

            if ($source !== null && ($record['source'] ?? null) !== $source) {
                return false;
            }

            return true;
        }));

        $filtered = $status === 'all'
            ? $records
            : array_values(array_filter($records, static fn (array $record): bool => ($record['status'] ?? null) === $status));

        usort($filtered, static function (array $left, array $right): int {
            $rightCreatedAt = is_string($right['createdAt'] ?? null) ? $right['createdAt'] : '';
            $leftCreatedAt = is_string($left['createdAt'] ?? null) ? $left['createdAt'] : '';
            $created = strcmp($rightCreatedAt, $leftCreatedAt);

            if ($created !== 0) {
                return $created;
            }

            $rightId = is_string($right['id'] ?? null) ? $right['id'] : '';
            $leftId = is_string($left['id'] ?? null) ? $left['id'] : '';

            return strcmp($rightId, $leftId);
        });

        $items = array_map(
            $this->summaryItem(...),
            array_slice($filtered, 0, $limit),
        );

        $payload = [
            'schema' => 'iak.feedback.list.v1',
            'status' => 'passed',
            'filters' => [
                'status' => $status,
                'surface' => $surface,
                'source' => $source,
                'limit' => $limit,
            ],
            'counts' => [
                'total' => count($records),
                'returned' => count($items),
                'pending' => $this->countStatus($records, 'pending'),
                'inProgress' => $this->countStatus($records, 'in_progress'),
                'resolved' => $this->countStatus($records, 'resolved'),
                'wontFix' => $this->countStatus($records, 'wont_fix'),
                'duplicate' => $this->countStatus($records, 'duplicate'),
            ],
            'items' => $items,
            'artifacts' => [
                'store' => [
                    'kind' => 'json',
                    'path' => $store->feedbackPath(),
                ],
            ],
            'errors' => [],
        ];

        return $this->respond($payload, sprintf('Found %d feedback record(s).', count($items)));
    }

    private function show(FeedbackStore $store): int
    {
        $id = $this->requiredId('show');
        $record = $store->find($id);

        if ($record === null) {
            throw $this->notFound($store, $id);
        }

        return $this->respond([
            'schema' => 'iak.feedback.show.v1',
            'status' => 'passed',
            'record' => $record,
            'artifacts' => [
                'record' => [
                    'kind' => 'json',
                    'path' => $store->recordPath($id),
                ],
            ],
            'errors' => [],
        ], "Feedback record {$id} found.");
    }

    private function resolve(FeedbackStore $store, FeedbackEvidenceValidator $validator): int
    {
        $id = $this->requiredId('resolve');
        $record = $store->find($id);

        if ($record === null) {
            throw $this->notFound($store, $id);
        }

        $currentStatus = is_string($record['status'] ?? null) ? $record['status'] : null;

        if (! in_array($currentStatus, ['pending', 'in_progress'], true)) {
            throw new FeedbackException(
                'feedback.invalid_transition',
                'Feedback record '.$id.' cannot be resolved from status ['.($currentStatus ?? 'unknown').'].',
                self::FAILURE,
                $store->recordPath($id),
                ['status' => $currentStatus],
            );
        }

        $evidencePath = $this->nullableStringOption('evidence');

        if ($evidencePath === null) {
            throw new FeedbackException(
                'feedback.evidence_required',
                'Resolving feedback requires an evidence path.',
                self::INVALID,
                $store->recordPath($id),
            );
        }

        $validated = $validator->validate($evidencePath, $id);
        $evidence = $validated['evidence'];
        $copiedTo = $store->writeResolutionEvidence($id, $evidence);
        $resolution = $this->resolution($evidence, $validated['path'], $copiedTo);
        $updated = $record;
        $updated['status'] = 'resolved';
        $updated['resolution'] = $resolution;
        $updated['updatedAt'] = $resolution['resolvedAt'];

        $store->writeRecord($id, $updated);

        return $this->respond([
            'schema' => 'iak.feedback.resolve.v1',
            'status' => 'resolved',
            'record' => $updated,
            'resolution' => $resolution,
            'artifacts' => [
                'record' => [
                    'kind' => 'json',
                    'path' => $store->recordPath($id),
                ],
                'evidence' => [
                    'kind' => 'json',
                    'path' => $copiedTo,
                ],
            ],
            'errors' => [],
        ], "Feedback record {$id} resolved.");
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function summaryItem(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'status' => $record['status'] ?? null,
            'surface' => $record['surface'] ?? null,
            'source' => $record['source'] ?? null,
            'producer' => $record['producer'] ?? null,
            'message' => $record['message'] ?? null,
            'target' => $record['target'] ?? (object) [],
            'attachments' => $record['attachments'] ?? (object) [],
            'createdAt' => $record['createdAt'] ?? null,
            'updatedAt' => $record['updatedAt'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function countStatus(array $records, string $status): int
    {
        return count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? null) === $status));
    }

    private function statusFilter(): string
    {
        $status = $this->nullableStringOption('status') ?? 'pending';

        if ($status === 'all' || in_array($status, FeedbackStore::STATUSES, true)) {
            return $status;
        }

        throw new FeedbackException(
            'feedback.invalid_status',
            'Feedback status filter must be pending, in_progress, resolved, wont_fix, duplicate, or all.',
            self::INVALID,
            null,
            ['status' => $status],
        );
    }

    private function limit(): int
    {
        $limit = $this->nullableStringOption('limit') ?? '50';

        if (filter_var($limit, FILTER_VALIDATE_INT) === false || (int) $limit < 1) {
            throw new FeedbackException(
                'feedback.invalid_limit',
                'Feedback list limit must be a positive integer.',
                self::INVALID,
                null,
                ['limit' => $limit],
            );
        }

        return (int) $limit;
    }

    private function requiredId(string $action): string
    {
        $id = $this->argument('id');

        if (! is_string($id) || trim($id) === '') {
            throw new FeedbackException(
                'feedback.id_required',
                "The {$action} action requires a feedback id.",
                self::INVALID,
            );
        }

        return trim($id);
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
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function resolution(array $evidence, string $linkedEvidence, string $copiedTo): array
    {
        $existingResolution = ($evidence['schema'] ?? null) === 'iak.feedback.resolution.v1' ? $evidence : [];
        $evidenceSummary = $this->evidenceSummary($evidence);
        $summary = $this->nullableStringOption('summary') ?? $evidenceSummary ?: 'Resolved with linked evidence.';
        $resolvedAt = gmdate('Y-m-d\TH:i:s\Z');

        return [
            ...$existingResolution,
            'schema' => 'iak.feedback.resolution.v1',
            'status' => 'resolved',
            'summary' => $summary,
            'reason' => $existingResolution['reason'] ?? null,
            'duplicateOf' => $existingResolution['duplicateOf'] ?? null,
            'changedFiles' => $existingResolution['changedFiles'] ?? $this->arrayValue($evidence, ['changedFiles'], ['resolution', 'changedFiles']),
            'commandsRun' => $existingResolution['commandsRun'] ?? $this->arrayValue($evidence, ['commandsRun'], ['resolution', 'commandsRun'], ['commands']),
            'artifacts' => $existingResolution['artifacts'] ?? $this->arrayValue($evidence, ['artifacts'], ['resolution', 'artifacts']),
            'linkedEvidence' => $linkedEvidence,
            'evidenceCopiedTo' => $copiedTo,
            'evidenceSummary' => $evidenceSummary,
            'resolver' => $existingResolution['resolver'] ?? [
                'kind' => 'agent',
                'id' => (string) (getenv('IAK_AGENT_ID') ?: 'iak:feedback'),
            ],
            'resolvedAt' => $resolvedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function evidenceSummary(array $evidence): string
    {
        foreach ([
            ['summary'],
            ['resolution', 'summary'],
            ['evidence', 'summary'],
            ['message'],
        ] as $path) {
            $value = $this->valueAt($evidence, $path);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  ...$paths
     * @return array<int|string, mixed>
     */
    private function arrayValue(array $source, array ...$paths): array
    {
        foreach ($paths as $path) {
            $value = $this->valueAt($source, $path);

            if (is_array($value)) {
                $map = ArrayData::stringMap($value);

                return $map !== [] ? $map : array_values($value);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $path
     */
    private function valueAt(array $source, array $path): mixed
    {
        $value = $source;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function notFound(FeedbackStore $store, string $id): FeedbackException
    {
        return new FeedbackException(
            'feedback.not_found',
            "Feedback record {$id} was not found.",
            self::INVALID,
            $store->recordPath($id),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(array $payload, string $humanLine, int $status = self::SUCCESS): int
    {
        if ($this->shouldEmitJson()) {
            return $this->emitJson($payload, $status);
        }

        $this->line($humanLine);

        return $status;
    }

    private function failWithFeedbackError(FeedbackException $exception): int
    {
        $payload = [
            'schema' => 'iak.error.v1',
            'status' => 'failed',
            'error' => [
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->filePath(),
                'line' => null,
                'details' => $exception->details() === [] ? (object) [] : $exception->details(),
            ],
        ];

        if ($this->shouldEmitJson()) {
            return $this->emitJson($payload, $exception->exitCode());
        }

        $this->error($exception->getMessage());

        return $exception->exitCode();
    }

    private function shouldEmitJson(): bool
    {
        return getenv('IAK_AGENT') === '1' || (bool) $this->option('json');
    }

    private function configString(string $key, string $default): string
    {
        $value = config($key, $default);

        return $value !== '' ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitJson(array $payload, int $status): int
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        if ((bool) $this->option('pretty')) {
            $options |= JSON_PRETTY_PRINT;
        }

        try {
            $this->output->writeln(json_encode($payload, $options));
        } catch (JsonException $exception) {
            $this->output->writeln(json_encode([
                'schema' => 'iak.error.v1',
                'status' => 'failed',
                'error' => [
                    'code' => 'feedback.json_encode_failed',
                    'message' => 'Unable to encode feedback command output.',
                    'file' => null,
                    'line' => null,
                    'details' => ['jsonError' => $exception->getMessage()],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return 4;
        }

        return $status;
    }
}
