<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InertiaAgentKit\Audit\Auditor;
use InertiaAgentKit\Support\Files;
use InertiaAgentKit\Support\ProjectPaths;
use JsonException;
use Throwable;

final class AuditCommand extends Command
{
    protected $signature = 'iak:audit
        {--json : Emit one machine-readable JSON response}
        {--pretty : Pretty-print JSON when JSON output is active}
        {--run-id= : Optional run id for deterministic tests}
        {--config= : Optional config path, default config/inertia-agent-kit.php}';

    protected $description = 'Audit an Inertia frontend against Inertia Agent Kit conventions.';

    public function handle(): int
    {
        $command = $this->getName() ?? 'iak:audit';
        $paths = new ProjectPaths($this->laravel);
        $runId = $this->resolveRunId();

        if (! $this->isValidRunId($runId)) {
            $payload = $this->blockedPayload(
                $command,
                'run_invalid',
                '.iak/runs/run_invalid/audit.json',
                'iak.usage.invalid_run_id',
                'Run id may contain only letters, numbers, dots, underscores, and dashes.',
                ['runId' => $runId]
            );

            $this->writeArtifact($payload, $payload['artifacts']['audit']['path']);

            return $this->emitPayload($payload, self::INVALID);
        }

        $artifactPath = $this->artifactPath($paths, $runId, $this->defaultConfig());

        try {
            $config = $this->auditConfig($paths);
            $artifactPath = $this->artifactPath($paths, $runId, $config);
            $configErrors = $this->validateConfig($config);

            if ($configErrors !== []) {
                $payload = $this->blockedPayload(
                    $command,
                    $runId,
                    $artifactPath,
                    'iak.config.invalid',
                    'Audit config is invalid.',
                    ['errors' => $configErrors],
                    $config
                );

                $this->writeArtifact($payload, $artifactPath);

                return $this->emitPayload($payload, self::INVALID);
            }

            $result = (new Auditor($this->laravel))->run($config);
            $payload = $this->completedPayload($command, $runId, $artifactPath, $config, $result, []);
            $this->writeArtifact($payload, $artifactPath);

            return $this->emitPayload($payload, $result['status'] === 'passed' ? self::SUCCESS : self::FAILURE);
        } catch (JsonException $exception) {
            $payload = $this->blockedPayload(
                $command,
                $runId,
                $artifactPath,
                'iak.json.encode_failed',
                $exception->getMessage()
            );

            return $this->emitPayload($payload, self::INVALID);
        } catch (Throwable $exception) {
            $payload = $this->blockedPayload(
                $command,
                $runId,
                $artifactPath,
                'iak.config.load_failed',
                $exception->getMessage()
            );

            $this->writeArtifact($payload, $artifactPath);

            return $this->emitPayload($payload, self::INVALID);
        }
    }

    private function resolveRunId(): string
    {
        $runId = $this->option('run-id');

        if (is_string($runId) && $runId !== '') {
            return $runId;
        }

        return 'run_'.strtolower((string) Str::ulid());
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
    private function auditConfig(ProjectPaths $paths): array
    {
        $baseConfig = $this->defaultConfig();
        $configPath = $this->option('config');

        if (! is_string($configPath) || $configPath === '') {
            return $baseConfig;
        }

        $absoluteConfigPath = $paths->absolute($configPath);

        if (! is_file($absoluteConfigPath) || ! is_readable($absoluteConfigPath)) {
            throw new \RuntimeException("Config file [{$configPath}] is not readable.");
        }

        $loaded = require $absoluteConfigPath;

        if (! is_array($loaded)) {
            throw new \RuntimeException("Config file [{$configPath}] must return an array.");
        }

        return array_replace_recursive($baseConfig, $loaded);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array{code: string, message: string}>
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
                $errors[] = ['code' => "iak.config.{$key}_invalid", 'message' => $message];
            }
        }

        $paths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];

        foreach (['root', 'features', 'components_ui', 'components_app', 'runs'] as $key) {
            if (! isset($paths[$key]) || ! is_string($paths[$key]) || $paths[$key] === '') {
                $errors[] = ['code' => "iak.config.paths.{$key}_invalid", 'message' => "Config key [paths.{$key}] must be a non-empty string."];
            }
        }

        $generated = isset($config['generated']) && is_array($config['generated']) ? $config['generated'] : [];

        foreach (['type_alias', 'types', 'routes', 'actions'] as $key) {
            if (! isset($generated[$key]) || ! is_string($generated[$key]) || $generated[$key] === '') {
                $errors[] = ['code' => "iak.config.generated.{$key}_invalid", 'message' => "Config key [generated.{$key}] must be a non-empty string."];
            }
        }

        if (! isset($config['forbidden_folders']) || ! is_array($config['forbidden_folders'])) {
            $errors[] = ['code' => 'iak.config.forbidden_folders_invalid', 'message' => 'Config key [forbidden_folders] must be an array.'];
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function artifactPath(ProjectPaths $paths, string $runId, array $config): string
    {
        $configPaths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];
        $runsPath = isset($configPaths['runs']) && is_string($configPaths['runs']) && $configPaths['runs'] !== ''
            ? $configPaths['runs']
            : '.iak/runs';

        return $paths->relative($paths->join($runsPath, $runId, 'audit.json'));
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
     * @param list<array<string, mixed>> $errors
     *
     * @return array<string, mixed>
     */
    private function completedPayload(string $command, string $runId, string $artifactPath, array $config, array $result, array $errors): array
    {
        return [
            'schema' => (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1'),
            'event' => (string) ($config['json_schemas']['audit_completed'] ?? 'iak.audit.completed'),
            'version' => 1,
            'command' => $command,
            'runId' => $runId,
            'status' => $result['status'],
            'summary' => $this->summary($result['status'], $result['totals']['errors']),
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
            'errors' => $errors,
            'meta' => $this->meta($config),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $config
     *
     * @return array<string, mixed>
     */
    private function blockedPayload(
        string $command,
        string $runId,
        string $artifactPath,
        string $code,
        string $message,
        array $context = [],
        ?array $config = null
    ): array {
        $config ??= $this->defaultConfig();
        $errors = [[
            'code' => $code,
            'message' => $message,
            'context' => $context === [] ? (object) [] : $context,
        ]];

        return [
            'schema' => (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1'),
            'event' => (string) ($config['json_schemas']['audit_completed'] ?? 'iak.audit.completed'),
            'version' => 1,
            'command' => $command,
            'runId' => $runId,
            'status' => 'blocked',
            'summary' => 'Audit blocked: '.$message,
            'totals' => [
                'checks' => 0,
                'passed' => 0,
                'failed' => 0,
                'blocked' => 0,
                'findings' => 0,
                'errors' => count($errors),
                'warnings' => 0,
            ],
            'checks' => [],
            'violations' => [],
            'artifacts' => [
                'audit' => [
                    'kind' => 'json',
                    'path' => $artifactPath,
                    'schema' => (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1'),
                ],
            ],
            'nextActions' => [],
            'errors' => $errors,
            'meta' => $this->meta($config),
        ];
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
            'createdAt' => gmdate('c'),
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => '0.1.0',
            'adapter' => 'laravel-inertia-'.$adapter,
            'configHash' => 'sha256:'.hash('sha256', json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        ];
    }

    private function summary(string $status, int $errors): string
    {
        if ($status === 'passed') {
            return 'Audit passed: 0 errors.';
        }

        return 'Audit failed: '.$errors.' '.$this->plural('error', $errors).'.';
    }

    private function plural(string $word, int $count): string
    {
        return $count === 1 ? $word : $word.'s';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeArtifact(array $payload, string $artifactPath): void
    {
        (new Files(new ProjectPaths($this->laravel)))->writeJson($artifactPath, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emitPayload(array $payload, int $exitCode): int
    {
        if ($this->shouldEmitJson()) {
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

            if ((bool) $this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->output->writeln(json_encode($payload, $flags));

            return $exitCode;
        }

        $this->line((string) $payload['summary']);
        $this->line('Artifact: '.$payload['artifacts']['audit']['path']);

        return $exitCode;
    }

    private function shouldEmitJson(): bool
    {
        return getenv('IAK_AGENT') === '1' || (bool) $this->option('json');
    }
}
