<?php

declare(strict_types=1);

namespace InertiaAgentKit\Handoff;

use Illuminate\Support\Str;
use InertiaAgentKit\Support\ArrayData;
use Throwable;

final readonly class HandoffCreator
{
    private ChangedFileParser $changedFileParser;

    public function __construct(?ChangedFileParser $changedFileParser = null)
    {
        $this->changedFileParser = $changedFileParser ?? new ChangedFileParser;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function create(array $input, array $config = []): array
    {
        $runId = $this->stringInput($input, ['runId', 'run-id']) ?? $this->generateRunId();
        $requestedStatus = strtolower($this->stringInput($input, ['status']) ?? 'completed');
        $changedFileEntries = $this->stringListInput($input, ['changedFile', 'changed-file']);
        $parsed = $this->changedFileParser->parse($changedFileEntries);
        $errors = $parsed['errors'];
        $changedFiles = $this->mergeChangedFiles(
            $this->groupedChangedFiles($input['changedFiles'] ?? null),
            $parsed['changedFiles']
        );
        $schema = $this->configString($config, ['json_schemas', 'handoff'], 'iak.handoff.v1');
        $auditPath = $this->stringInput($input, ['audit']);
        $testsPath = $this->stringInput($input, ['tests']);
        $verifyPath = $this->stringInput($input, ['verify']);

        return [
            'schema' => $schema,
            'version' => 1,
            'command' => $this->stringInput($input, ['command']) ?? 'iak:handoff',
            'runId' => $runId,
            'task' => $this->stringInput($input, ['task']),
            'status' => $errors === [] ? $requestedStatus : 'blocked',
            'summary' => $this->stringInput($input, ['summary']) ?? '',
            'changedFiles' => $changedFiles === [] ? (object) [] : $changedFiles,
            'evidence' => [
                'audit' => $this->artifactEvidence(
                    $auditPath,
                    $this->configString($config, ['json_schemas', 'audit'], 'iak.audit.v1'),
                    withStatus: true
                ),
                'tests' => $this->artifactEvidence($testsPath, withStatus: true),
                'verify' => $this->artifactEvidence(
                    $verifyPath,
                    $this->configString($config, ['json_schemas', 'verify'], 'iak.verify.v1')
                ),
                'feedback' => [
                    'unresolved' => $this->integerInput($input, ['feedbackUnresolved', 'feedback-unresolved']),
                ],
            ],
            'artifacts' => [
                'handoff' => [
                    'kind' => 'json',
                    'path' => $this->handoffArtifactPath($runId, $config),
                    'schema' => $schema,
                    'status' => 'not_written',
                ],
            ],
            'notes' => $this->stringListInput($input, ['notes', 'note']),
            'nextActions' => $this->nextActions($input),
            'errors' => $errors,
            'meta' => [
                'createdAt' => gmdate('c'),
                'package' => 'fbarrento/inertia-agent-kit',
                'iakVersion' => $this->configString($config, ['iakVersion'], '0.1.0'),
                'mode' => 'first_port',
                'requested' => [
                    'status' => $requestedStatus,
                    'changedFile' => $changedFileEntries,
                    'hasGroupedChangedFiles' => array_key_exists('changedFiles', $input),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $left
     * @param  array<string, list<array<string, mixed>>>  $right
     * @return array<string, list<array<string, mixed>>>
     */
    private function mergeChangedFiles(array $left, array $right): array
    {
        foreach ($right as $role => $entries) {
            $left[$role] ??= [];
            array_push($left[$role], ...$entries);
        }

        return $left;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupedChangedFiles(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $changedFiles = [];

        foreach ($value as $role => $entries) {
            if (! is_string($role) || ! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $entry = ArrayData::stringMap($entry);
                $path = $this->stringInput($entry, ['path']);
                $action = $this->stringInput($entry, ['action']);

                if ($path === null || $action === null) {
                    continue;
                }

                $changedFiles[$role] ??= [];
                $changedFiles[$role][] = [
                    'path' => $path,
                    'action' => strtolower($action),
                ];
            }
        }

        return $changedFiles;
    }

    /**
     * @return array<string, mixed>
     */
    private function artifactEvidence(?string $path, ?string $schema = null, bool $withStatus = false): array
    {
        $evidence = [];

        if ($withStatus) {
            $evidence['status'] = $path === null ? null : 'pending';
        }

        $evidence['artifact'] = $path === null ? null : $this->artifactReference($path, $schema);

        return $evidence;
    }

    /**
     * @return array<string, string>
     */
    private function artifactReference(string $path, ?string $schema = null): array
    {
        $artifact = [
            'kind' => 'json',
            'path' => $path,
        ];

        if ($schema !== null) {
            $artifact['schema'] = $schema;
        }

        return $artifact;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function nextActions(array $input): array
    {
        $actions = [];
        $value = $input['nextActions'] ?? null;

        if (is_array($value)) {
            $items = $this->isAssociativeNextAction($value) ? [$value] : $value;

            foreach ($items as $item) {
                if (is_array($item)) {
                    $item = ArrayData::stringMap($item);

                    if ($item !== []) {
                        $actions[] = $item;
                    }

                    continue;
                }

                if (is_scalar($item) && ! is_bool($item) && trim((string) $item) !== '') {
                    $actions[] = $this->nextActionFromSummary((string) $item);
                }
            }
        }

        foreach ($this->stringListInput($input, ['nextAction', 'next-action']) as $summary) {
            $actions[] = $this->nextActionFromSummary($summary);
        }

        return $actions;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isAssociativeNextAction(array $value): bool
    {
        return array_key_exists('summary', $value) || array_key_exists('type', $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function nextActionFromSummary(string $summary): array
    {
        return [
            'type' => 'follow_up',
            'summary' => trim($summary),
            'blocking' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $keys
     */
    private function stringInput(array $input, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if (! is_scalar($value) || is_bool($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function stringListInput(array $input, array $keys): array
    {
        $items = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (! is_scalar($item) || is_bool($item)) {
                        continue;
                    }

                    $item = trim((string) $item);

                    if ($item !== '') {
                        $items[] = $item;
                    }
                }

                continue;
            }

            if (is_scalar($value) && ! is_bool($value)) {
                $value = trim((string) $value);

                if ($value !== '') {
                    $items[] = $value;
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $keys
     */
    private function integerInput(array $input, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if (is_int($value)) {
                return $value;
            }

            if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
                return (int) trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $keys
     */
    private function configString(array $config, array $keys, string $default): string
    {
        $value = $config;

        foreach ($keys as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return $default;
            }

            $value = $value[$key];
        }

        if (! is_scalar($value) || is_bool($value) || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function handoffArtifactPath(string $runId, array $config): string
    {
        $runsPath = $this->configString($config, ['paths', 'runs'], '.iak/runs');
        $runsPath = trim(str_replace('\\', '/', $runsPath), '/');

        if ($runsPath === '') {
            $runsPath = '.iak/runs';
        }

        return $runsPath.'/'.$runId.'/handoff.json';
    }

    private function generateRunId(): string
    {
        try {
            if (class_exists(Str::class)) {
                return 'run_'.strtolower((string) Str::ulid());
            }
        } catch (Throwable) {
            // Fall through to the local fallback below.
        }

        try {
            return 'run_'.strtolower(bin2hex(random_bytes(8)));
        } catch (Throwable) {
            return 'run_'.strtolower(str_replace('.', '', uniqid('', true)));
        }
    }
}
