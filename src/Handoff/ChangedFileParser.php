<?php

declare(strict_types=1);

namespace InertiaAgentKit\Handoff;

final class ChangedFileParser
{
    /**
     * @var list<string>
     */
    private const ROLES = [
        'page',
        'feature',
        'story',
        'component-ui',
        'component-app',
        'layout',
        'type',
        'config',
        'test',
        'docs',
        'boost',
        'package',
        'resource',
        'other',
    ];

    /**
     * @var list<string>
     */
    private const ACTIONS = [
        'create',
        'modify',
        'delete',
        'rename',
    ];

    /**
     * @param list<string> $entries
     *
     * @return array{changedFiles: array<string, list<array{path: string, action: string}>>, errors: list<array<string, mixed>>}
     */
    public function parse(array $entries): array
    {
        $changedFiles = [];
        $errors = [];

        foreach (array_values($entries) as $index => $entry) {
            if (! is_scalar($entry) || is_bool($entry)) {
                $errors[] = $this->error(
                    'changed_file.invalid_entry',
                    'Changed file entries must be strings in role:action:path format.',
                    $index,
                    $entry
                );

                continue;
            }

            $rawEntry = trim((string) $entry);
            $parts = explode(':', $rawEntry, 3);

            if (count($parts) !== 3) {
                $errors[] = $this->error(
                    'changed_file.invalid_format',
                    'Changed file entries must use role:action:path format.',
                    $index,
                    $rawEntry
                );

                continue;
            }

            $role = strtolower(trim($parts[0]));
            $action = strtolower(trim($parts[1]));
            $path = trim($parts[2]);
            $entryErrors = [];

            if (! in_array($role, self::ROLES, true)) {
                $entryErrors[] = $this->error(
                    'changed_file.invalid_role',
                    'Changed file role is not supported.',
                    $index,
                    $rawEntry,
                    [
                        'role' => $role,
                        'allowed' => self::ROLES,
                    ]
                );
            }

            if (! in_array($action, self::ACTIONS, true)) {
                $entryErrors[] = $this->error(
                    'changed_file.invalid_action',
                    'Changed file action is not supported.',
                    $index,
                    $rawEntry,
                    [
                        'action' => $action,
                        'allowed' => self::ACTIONS,
                    ]
                );
            }

            $pathResult = $this->normalizePath($path);

            if ($pathResult['error'] !== null) {
                $entryErrors[] = $this->error(
                    'changed_file.invalid_path',
                    $pathResult['error']['message'],
                    $index,
                    $rawEntry,
                    [
                        'path' => $path,
                        'reason' => $pathResult['error']['reason'],
                    ]
                );
            }

            if ($entryErrors !== []) {
                array_push($errors, ...$entryErrors);

                continue;
            }

            $changedFiles[$role] ??= [];
            $changedFiles[$role][] = [
                'path' => $pathResult['path'],
                'action' => $action,
            ];
        }

        return [
            'changedFiles' => $changedFiles,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{
     *     path: string,
     *     error: array{reason: string, message: string}|null
     * }
     */
    private function normalizePath(string $path): array
    {
        $path = trim($path);

        if ($path === '') {
            return $this->pathError('empty', 'Changed file path must not be empty.');
        }

        if (str_contains($path, "\0")) {
            return $this->pathError('null_byte', 'Changed file path must not contain null bytes.');
        }

        $normalized = str_replace('\\', '/', $path);

        if (
            str_starts_with($normalized, '/')
            || str_starts_with($normalized, '//')
            || preg_match('/^[A-Za-z]:/', $normalized) === 1
        ) {
            return $this->pathError('absolute', 'Changed file path must be project-relative.');
        }

        $segments = [];

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return $this->pathError('traversal', 'Changed file path must not contain traversal segments.');
            }

            if ($segment === '.git') {
                return $this->pathError('git', 'Changed file path must not target .git.');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return $this->pathError('empty', 'Changed file path must not be empty.');
        }

        return [
            'path' => implode('/', $segments),
            'error' => null,
        ];
    }

    /**
     * @return array{
     *     path: string,
     *     error: array{reason: string, message: string}
     * }
     */
    private function pathError(string $reason, string $message): array
    {
        return [
            'path' => '',
            'error' => [
                'reason' => $reason,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param mixed $entry
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     */
    private function error(string $code, string $message, int $index, mixed $entry, array $details = []): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'file' => null,
            'line' => null,
            'details' => [
                'index' => $index,
                'entry' => $entry,
                ...$details,
            ],
        ];
    }
}
