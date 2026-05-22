<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use JsonException;

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
        {--feedback-unresolved= : Optional unresolved feedback artifact path}
        {--note=* : Handoff note}
        {--next-action=* : Follow-up action}
        {--json : Emit one machine-readable JSON response}
        {--pretty : Pretty-print JSON when JSON output is active}';

    protected $description = 'Create or validate an Inertia Agent Kit handoff artifact.';

    public function handle(): int
    {
        $payload = $this->placeholderPayload();

        return $this->emitPayload($payload, self::INVALID);
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderPayload(): array
    {
        $config = $this->config();
        $schema = (string) ($config['json_schemas']['handoff'] ?? 'iak.handoff.v1');
        $action = $this->action();
        $path = $this->nullableStringArgument('path');
        $runId = $this->nullableStringOption('run-id') ?? 'run_not_created';
        $artifactPath = $path ?? $this->handoffArtifactPath($runId, $config);
        $message = 'The iak:handoff command is registered, but create and validate behavior is not implemented in this baseline slice.';

        return [
            'schema' => $schema,
            'version' => 1,
            'command' => $this->getName() ?? 'iak:handoff',
            'action' => $action,
            'runId' => $runId,
            'task' => $this->nullableStringOption('task'),
            'status' => 'blocked',
            'summary' => 'Handoff blocked: implementation is planned but not available yet.',
            'changedFiles' => (object) [],
            'evidence' => [
                'audit' => $this->artifactReference($this->nullableStringOption('audit'), (string) ($config['json_schemas']['audit'] ?? 'iak.audit.v1')),
                'verify' => $this->artifactReference($this->nullableStringOption('verify'), (string) ($config['json_schemas']['verify'] ?? 'iak.verify.v1')),
                'tests' => $this->artifactReference($this->nullableStringOption('tests')),
                'feedback' => [
                    'status' => 'not_evaluated',
                    'unresolved' => $this->artifactReference($this->nullableStringOption('feedback-unresolved')),
                ],
            ],
            'artifacts' => [
                'handoff' => [
                    'kind' => 'json',
                    'path' => $artifactPath,
                    'schema' => $schema,
                    'status' => 'not_written',
                ],
            ],
            'notes' => $this->stringListOption('note'),
            'nextActions' => [[
                'type' => 'implement_handoff_command',
                'summary' => 'Replace this placeholder with real iak:handoff create and validate behavior.',
                'blocking' => true,
            ]],
            'errors' => [[
                'code' => 'handoff.not_implemented',
                'message' => $message,
                'context' => [
                    'action' => $action,
                    'path' => $path,
                ],
            ]],
            'meta' => [
                'createdAt' => gmdate('c'),
                'package' => 'fbarrento/inertia-agent-kit',
                'iakVersion' => '0.1.0',
                'mode' => 'baseline_placeholder',
                'requested' => [
                    'status' => $this->nullableStringOption('status') ?? 'completed',
                    'changedFile' => $this->stringListOption('changed-file'),
                    'changedFiles' => $this->nullableStringOption('changed-files'),
                    'nextAction' => $this->stringListOption('next-action'),
                ],
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
     * @return array{kind: string, path: string, schema?: string, status: string}|null
     */
    private function artifactReference(?string $path, ?string $schema = null): ?array
    {
        if ($path === null) {
            return null;
        }

        $artifact = [
            'kind' => 'json',
            'path' => $path,
            'status' => 'not_evaluated',
        ];

        if ($schema !== null) {
            $artifact['schema'] = $schema;
        }

        return $artifact;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function handoffArtifactPath(string $runId, array $config): string
    {
        $paths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];
        $runsPath = isset($paths['runs']) && is_string($paths['runs']) && $paths['runs'] !== ''
            ? trim($paths['runs'], '/')
            : '.iak/runs';

        return $runsPath.'/'.$runId.'/handoff.json';
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

        $this->line((string) $payload['summary']);

        return $status;
    }

    private function shouldEmitJson(): bool
    {
        return getenv('IAK_AGENT') === '1' || (bool) $this->option('json');
    }
}
