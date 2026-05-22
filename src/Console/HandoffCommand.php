<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use InertiaAgentKit\Handoff\HandoffCreator;
use InertiaAgentKit\Handoff\HandoffValidator;
use InertiaAgentKit\Support\Files;
use InertiaAgentKit\Support\ProjectPaths;
use JsonException;
use Throwable;

final class HandoffCommand extends Command
{
    protected $signature = 'iak:handoff
        {action=create : Handoff action: create or validate}
        {path? : Optional handoff artifact path for validate}
        {--run-id= : Optional handoff run id}
        {--task= : Task description for the handoff}
        {--summary= : Summary of the completed work}
        {--status=completed : Requested handoff status}
        {--changed-file=* : Changed file entry as role:action:path}
        {--changed-files= : Optional JSON artifact with grouped changedFiles}
        {--verify= : Optional iak.verify.v1 artifact path}
        {--audit= : Optional iak.audit.v1 artifact path}
        {--tests= : Optional tests artifact path}
        {--feedback-unresolved= : Optional unresolved feedback count}
        {--note=* : Handoff note}
        {--next-action=* : Follow-up action}
        {--json : Emit one machine-readable JSON response}
        {--pretty : Pretty-print JSON when JSON output is active}';

    protected $description = 'Create or validate an Inertia Agent Kit handoff artifact.';

    public function handle(): int
    {
        return match ($this->action()) {
            'create' => $this->handleCreate(),
            'validate' => $this->handleValidate(),
            default => $this->emitPayload($this->commandErrorPayload(
                'handoff.action.invalid',
                'Handoff action must be create or validate.',
                [
                    'action' => $this->action(),
                    'allowed' => ['create', 'validate'],
                ],
            ), self::INVALID),
        };
    }

    private function handleCreate(): int
    {
        $paths = new ProjectPaths($this->laravel);
        $files = new Files($paths);
        $config = $this->config();
        $input = $this->createInput();
        $changedFilesPath = $this->nullableStringOption('changed-files');

        if ($changedFilesPath !== null) {
            $changedFilesResult = $this->readChangedFilesPayload($paths, $changedFilesPath);

            if ($changedFilesResult['error'] !== null) {
                return $this->emitPayload($this->createErrorPayload(
                    $changedFilesResult['error'],
                    $input['runId'] ?? null,
                    $config,
                ), self::INVALID);
            }

            $input['changedFiles'] = $changedFilesResult['changedFiles'];
        }

        $payload = (new HandoffCreator())->create($input, $config);
        $artifactPath = $this->handoffArtifactPathFromPayload($payload) ?? $this->handoffArtifactPath((string) $payload['runId'], $config);
        $payload['artifacts']['handoff']['status'] = 'written';

        try {
            $files->writeJson($artifactPath, $payload);
        } catch (Throwable $exception) {
            return $this->emitPayload($this->createErrorPayload(
                $this->errorPayload('handoff.artifact.write_failed', 'Unable to write handoff artifact.', $artifactPath, [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]),
                $payload['runId'] ?? null,
                $config,
            ), self::INVALID);
        }

        $exitCode = $payload['status'] === 'blocked' || $payload['errors'] !== []
            ? self::INVALID
            : self::SUCCESS;

        return $this->emitPayload($payload, $exitCode);
    }

    private function handleValidate(): int
    {
        $paths = new ProjectPaths($this->laravel);
        $config = $this->config();
        $path = $this->validatePath($config);

        if ($path === null) {
            return $this->emitPayload($this->validationPayload(
                path: null,
                payload: null,
                valid: false,
                errors: [$this->errorPayload('handoff.path.required', 'Provide a handoff path or --run-id for validation.')],
                nextActions: [],
                meta: ['source' => 'missing_path'],
                config: $config,
            ), self::INVALID);
        }

        $readResult = $this->readJsonObject($paths, $path, 'handoff');

        if ($readResult['error'] !== null) {
            return $this->emitPayload($this->validationPayload(
                path: $readResult['path'] ?? $path,
                payload: null,
                valid: false,
                errors: [$readResult['error']],
                nextActions: [],
                meta: ['source' => 'read_failed'],
                config: $config,
            ), self::INVALID);
        }

        $payload = $readResult['payload'] ?? [];
        $result = (new HandoffValidator())->validate($payload, $paths->basePath());

        return $this->emitPayload($this->validationPayload(
            path: $readResult['path'] ?? $path,
            payload: $payload,
            valid: $result['valid'],
            errors: $result['errors'],
            nextActions: $result['nextActions'],
            meta: ['source' => 'validator'],
            config: $config,
        ), $result['valid'] ? self::SUCCESS : self::INVALID);
    }

    /**
     * @return array<string, mixed>
     */
    private function createInput(): array
    {
        return [
            'command' => $this->getName() ?? 'iak:handoff',
            'runId' => $this->nullableStringOption('run-id'),
            'task' => $this->nullableStringOption('task'),
            'summary' => $this->nullableStringOption('summary'),
            'status' => $this->nullableStringOption('status') ?? 'completed',
            'changedFile' => $this->stringListOption('changed-file'),
            'audit' => $this->nullableStringOption('audit'),
            'tests' => $this->nullableStringOption('tests'),
            'verify' => $this->nullableStringOption('verify'),
            'feedbackUnresolved' => $this->nullableStringOption('feedback-unresolved'),
            'note' => $this->stringListOption('note'),
            'nextAction' => $this->stringListOption('next-action'),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validatePath(array $config): ?string
    {
        $path = $this->nullableStringArgument('path');

        if ($path !== null) {
            return $path;
        }

        $runId = $this->nullableStringOption('run-id');

        if ($runId === null) {
            return null;
        }

        return $this->handoffArtifactPath($runId, $config);
    }

    /**
     * @return array{changedFiles: array<string, mixed>, error: array<string, mixed>|null}
     */
    private function readChangedFilesPayload(ProjectPaths $paths, string $path): array
    {
        $result = $this->readJsonObject($paths, $path, 'changed_files');

        if ($result['error'] !== null) {
            return [
                'changedFiles' => [],
                'error' => $result['error'],
            ];
        }

        $payload = $result['payload'] ?? [];
        $changedFiles = $payload['changedFiles'] ?? $payload;

        if (! is_array($changedFiles) || (array_is_list($changedFiles) && $changedFiles !== [])) {
            return [
                'changedFiles' => [],
                'error' => $this->errorPayload(
                    'handoff.changed_files.invalid_payload',
                    'Changed files artifact must contain a grouped changedFiles object.',
                    $result['path'],
                    ['field' => 'changedFiles'],
                ),
            ];
        }

        return [
            'changedFiles' => $changedFiles,
            'error' => null,
        ];
    }

    /**
     * @return array{path: string|null, payload: array<string, mixed>|null, error: array<string, mixed>|null}
     */
    private function readJsonObject(ProjectPaths $paths, string $path, string $source): array
    {
        $normalized = $this->normalizeProjectRelativePath($path);

        if ($normalized === null) {
            return [
                'path' => null,
                'payload' => null,
                'error' => $this->errorPayload(
                    "handoff.{$source}.path_invalid",
                    'Path must be project-relative and must not contain traversal or .git segments.',
                    $path,
                ),
            ];
        }

        $absolute = $paths->basePath($normalized);

        if (! is_file($absolute)) {
            return [
                'path' => $normalized,
                'payload' => null,
                'error' => $this->errorPayload(
                    "handoff.{$source}.missing",
                    "JSON artifact [{$normalized}] was not found.",
                    $normalized,
                ),
            ];
        }

        $contents = file_get_contents($absolute);

        if ($contents === false) {
            return [
                'path' => $normalized,
                'payload' => null,
                'error' => $this->errorPayload(
                    "handoff.{$source}.read_failed",
                    "JSON artifact [{$normalized}] could not be read.",
                    $normalized,
                ),
            ];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [
                'path' => $normalized,
                'payload' => null,
                'error' => $this->errorPayload(
                    "handoff.{$source}.invalid_json",
                    "JSON artifact [{$normalized}] is not valid JSON.",
                    $normalized,
                    ['jsonError' => $exception->getMessage()],
                ),
            ];
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return [
                'path' => $normalized,
                'payload' => null,
                'error' => $this->errorPayload(
                    "handoff.{$source}.invalid_payload",
                    "JSON artifact [{$normalized}] must contain a JSON object.",
                    $normalized,
                ),
            ];
        }

        return [
            'path' => $normalized,
            'payload' => $decoded,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param list<array<string, mixed>> $errors
     * @param list<array<string, mixed>> $nextActions
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function validationPayload(
        ?string $path,
        ?array $payload,
        bool $valid,
        array $errors,
        array $nextActions,
        array $meta,
        array $config,
    ): array {
        $runId = $this->runIdFromPayload($payload) ?? $this->nullableStringOption('run-id');
        return [
            'schema' => is_string($payload['schema'] ?? null) ? $payload['schema'] : (string) ($config['json_schemas']['handoff'] ?? 'iak.handoff.v1'),
            'command' => $this->getName() ?? 'iak:handoff',
            'action' => 'validate',
            'status' => $valid ? 'valid' : 'invalid',
            'summary' => $valid ? 'Handoff validation passed.' : 'Handoff validation failed.',
            'valid' => $valid,
            'path' => $path,
            ...($runId === null ? [] : ['runId' => $runId]),
            'errors' => $errors,
            'nextActions' => $nextActions,
            'meta' => [
                'createdAt' => gmdate('c'),
                'package' => 'fbarrento/inertia-agent-kit',
                'iakVersion' => $this->iakVersion($config),
                ...$meta,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $error
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function createErrorPayload(array $error, mixed $runId, array $config): array
    {
        $runId = is_scalar($runId) && ! is_bool($runId) && trim((string) $runId) !== ''
            ? trim((string) $runId)
            : 'run_not_created';

        return [
            'schema' => (string) ($config['json_schemas']['handoff'] ?? 'iak.handoff.v1'),
            'version' => 1,
            'command' => $this->getName() ?? 'iak:handoff',
            'action' => 'create',
            'runId' => $runId,
            'task' => $this->nullableStringOption('task'),
            'status' => 'blocked',
            'summary' => 'Handoff create blocked: '.$error['message'],
            'changedFiles' => (object) [],
            'evidence' => [],
            'artifacts' => [
                'handoff' => [
                    'kind' => 'json',
                    'path' => $this->handoffArtifactPath($runId, $config),
                    'schema' => (string) ($config['json_schemas']['handoff'] ?? 'iak.handoff.v1'),
                    'status' => 'not_written',
                ],
            ],
            'notes' => $this->stringListOption('note'),
            'nextActions' => [],
            'errors' => [$error],
            'meta' => [
                'createdAt' => gmdate('c'),
                'package' => 'fbarrento/inertia-agent-kit',
                'iakVersion' => $this->iakVersion($config),
                'mode' => 'command_error',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     */
    private function commandErrorPayload(string $code, string $message, array $details = []): array
    {
        $config = $this->config();
        $runId = $this->nullableStringOption('run-id') ?? 'run_not_created';

        return [
            'schema' => (string) ($config['json_schemas']['handoff'] ?? 'iak.handoff.v1'),
            'command' => $this->getName() ?? 'iak:handoff',
            'action' => $this->action(),
            'status' => 'blocked',
            'summary' => $message,
            'runId' => $runId,
            'errors' => [$this->errorPayload($code, $message, null, $details)],
            'nextActions' => [],
            'meta' => [
                'createdAt' => gmdate('c'),
                'package' => 'fbarrento/inertia-agent-kit',
                'iakVersion' => $this->iakVersion($config),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $config = config('inertia-agent-kit');

        return is_array($config) ? $config : [];
    }

    private function action(): string
    {
        $action = $this->argument('action');

        return is_string($action) && trim($action) !== '' ? strtolower(trim($action)) : 'create';
    }

    private function nullableStringArgument(string $argument): ?string
    {
        $value = $this->argument($argument);

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
     * @return list<string>
     */
    private function stringListOption(string $option): array
    {
        $value = $this->option($option);

        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $item = trim((string) $item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function handoffArtifactPath(string $runId, array $config): string
    {
        $paths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];
        $runsPath = isset($paths['runs']) && is_string($paths['runs']) && $paths['runs'] !== ''
            ? trim(str_replace('\\', '/', $paths['runs']), '/')
            : '.iak/runs';

        if ($runsPath === '') {
            $runsPath = '.iak/runs';
        }

        return $runsPath.'/'.$runId.'/handoff.json';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handoffArtifactPathFromPayload(array $payload): ?string
    {
        $artifact = $payload['artifacts']['handoff'] ?? null;

        if (! is_array($artifact)) {
            return null;
        }

        $path = $artifact['path'] ?? null;

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function runIdFromPayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $runId = $payload['runId'] ?? null;

        return is_string($runId) && trim($runId) !== '' ? trim($runId) : null;
    }

    private function normalizeProjectRelativePath(string $path): ?string
    {
        if (str_contains($path, "\0")) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\//', $path) === 1) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' || $segment === '.git') {
                return null;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return null;
        }

        return implode('/', $segments);
    }

    private function iakVersion(array $config): string
    {
        $version = $config['iakVersion'] ?? null;

        return is_scalar($version) && ! is_bool($version) && trim((string) $version) !== ''
            ? trim((string) $version)
            : '0.1.0';
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     */
    private function errorPayload(string $code, string $message, ?string $file = null, array $details = []): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'file' => $file,
            'line' => null,
            'details' => $details,
        ];
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

            try {
                $this->output->writeln(json_encode($payload, $flags));
            } catch (JsonException $exception) {
                $this->output->writeln(json_encode([
                    'schema' => 'iak.error.v1',
                    'status' => 'failed',
                    'error' => [
                        'code' => 'handoff.json_encode_failed',
                        'message' => 'Unable to encode handoff command output.',
                        'details' => ['jsonError' => $exception->getMessage()],
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return 4;
            }

            return $status;
        }

        $summary = isset($payload['summary']) && is_scalar($payload['summary'])
            ? (string) $payload['summary']
            : 'Handoff command finished.';

        $this->line($summary);

        return $status;
    }

    private function shouldEmitJson(): bool
    {
        return getenv('IAK_AGENT') === '1' || (bool) $this->option('json');
    }
}
