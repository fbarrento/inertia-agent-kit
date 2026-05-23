<?php

declare(strict_types=1);

namespace InertiaAgentKit\Handoff;

use InertiaAgentKit\Enum\ChangedFileAction;
use InertiaAgentKit\Enum\ChangedFileRole;
use InertiaAgentKit\Enum\EvidenceStatus;
use InertiaAgentKit\Enum\HandoffStatus;
use InertiaAgentKit\Support\ArrayData;

final class HandoffValidator
{
    private const SCHEMA = 'iak.handoff.v1';

    /** @var list<string> */
    private const REQUIRED_FIELDS = [
        'schema',
        'runId',
        'task',
        'status',
        'summary',
        'changedFiles',
        'evidence',
        'artifacts',
        'notes',
        'nextActions',
        'errors',
    ];

    /** @var list<array{code: string, message: string, file: string|null, line: int|null, details: array<string, mixed>}> */
    private array $errors = [];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, status: 'valid'|'invalid', errors: list<array<string, mixed>>, nextActions: list<array<string, mixed>>}
     */
    public function validate(array $payload, string $basePath): array
    {
        $this->errors = [];
        $basePath = $this->normalizeBasePath($basePath);

        $this->validateRequiredFields($payload);
        $this->validateSchema($payload);

        $status = $payload['status'] ?? null;
        $isCompleted = $status === HandoffStatus::Completed->value;

        if (array_key_exists('status', $payload) && ! is_string($status)) {
            $this->addError(
                'handoff.status.invalid',
                'Handoff status must be a string.',
                details: ['field' => 'status'],
            );
        }

        $changedFilePaths = $this->validateChangedFiles(
            $payload['changedFiles'] ?? null,
            array_key_exists('changedFiles', $payload),
            $isCompleted,
        );

        $this->validateEvidence(
            $payload['evidence'] ?? null,
            array_key_exists('evidence', $payload),
            $isCompleted,
            $basePath,
            $changedFilePaths,
        );

        $this->validateArtifacts(
            $payload['artifacts'] ?? null,
            array_key_exists('artifacts', $payload),
            $basePath,
            $changedFilePaths,
        );

        $this->validateNotes($payload['notes'] ?? null, array_key_exists('notes', $payload));
        $this->validateNextActions($payload['nextActions'] ?? null, array_key_exists('nextActions', $payload), $isCompleted);
        $this->validateErrors($payload['errors'] ?? null, array_key_exists('errors', $payload), $isCompleted);

        return [
            'valid' => $this->errors === [],
            'status' => $this->errors === [] ? HandoffStatus::Valid->value : HandoffStatus::Invalid->value,
            'errors' => $this->errors,
            'nextActions' => $this->extractNextActions($payload['nextActions'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateRequiredFields(array $payload): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                continue;
            }

            $this->addError(
                $this->requiredFieldCode($field),
                "Handoff payload is missing required field [{$field}].",
                details: ['field' => $field],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateSchema(array $payload): void
    {
        if (! array_key_exists('schema', $payload)) {
            return;
        }

        $schema = $payload['schema'];

        if ($schema !== self::SCHEMA) {
            $this->addError(
                'handoff.schema.invalid',
                'Handoff payload must use schema [iak.handoff.v1].',
                details: [
                    'expected' => self::SCHEMA,
                    'actual' => $schema,
                ],
            );
        }
    }

    /**
     * @return array<string, true>
     */
    private function validateChangedFiles(mixed $changedFiles, bool $isPresent, bool $isCompleted): array
    {
        if (! $isPresent) {
            return [];
        }

        if (! is_array($changedFiles) || (array_is_list($changedFiles) && $changedFiles !== [])) {
            $this->addError(
                'handoff.changed_files.invalid_type',
                'Handoff changedFiles must be an object grouped by role.',
                details: ['field' => 'changedFiles'],
            );

            return [];
        }

        $paths = [];
        $entryCount = 0;

        foreach ($changedFiles as $role => $entries) {
            if (! is_string($role) || ChangedFileRole::tryFrom($role) === null) {
                $this->addError(
                    'handoff.changed_files.role_invalid',
                    'Handoff changedFiles contains an unsupported role.',
                    details: [
                        'role' => $role,
                        'allowedRoles' => ChangedFileRole::values(),
                    ],
                );
            }

            if (! is_array($entries) || ! array_is_list($entries)) {
                $this->addError(
                    'handoff.changed_files.entries_invalid',
                    'Each changedFiles role must contain a list of file entries.',
                    details: ['role' => $role],
                );

                continue;
            }

            foreach ($entries as $index => $entry) {
                $entryCount++;

                if (! is_array($entry)) {
                    $this->addError(
                        'handoff.changed_files.entry_invalid',
                        'Each changed file entry must be an object.',
                        details: [
                            'role' => $role,
                            'index' => $index,
                        ],
                    );

                    continue;
                }

                if (! array_key_exists('path', $entry)) {
                    $this->addError(
                        'handoff.changed_files.path_missing',
                        'Each changed file entry must include a path.',
                        details: [
                            'role' => $role,
                            'index' => $index,
                        ],
                    );
                } else {
                    $normalizedPath = $this->projectRelativePath($entry['path']);

                    if ($normalizedPath === null) {
                        $this->addError(
                            'handoff.changed_files.path_invalid',
                            'Changed file paths must be project-relative and must not contain traversal or .git segments.',
                            details: [
                                'role' => $role,
                                'index' => $index,
                                'path' => $entry['path'],
                            ],
                        );
                    } else {
                        $paths[$normalizedPath] = true;
                    }
                }

                if (! array_key_exists('action', $entry)) {
                    $this->addError(
                        'handoff.changed_files.action_missing',
                        'Each changed file entry must include an action.',
                        details: [
                            'role' => $role,
                            'index' => $index,
                        ],
                    );
                } elseif (! is_string($entry['action']) || ChangedFileAction::tryFrom($entry['action']) === null) {
                    $this->addError(
                        'handoff.changed_files.action_invalid',
                        'Changed file actions must use the allowed action vocabulary.',
                        details: [
                            'role' => $role,
                            'index' => $index,
                            'action' => $entry['action'],
                            'allowedActions' => ChangedFileAction::values(),
                        ],
                    );
                }
            }
        }

        if ($isCompleted && $entryCount === 0) {
            $this->addError(
                'handoff.changed_files.empty',
                'Completed handoffs must include at least one changed file.',
                details: ['field' => 'changedFiles'],
            );
        }

        return $paths;
    }

    /**
     * @param  array<string, true>  $changedFilePaths
     */
    private function validateEvidence(
        mixed $evidence,
        bool $isPresent,
        bool $isCompleted,
        string $basePath,
        array $changedFilePaths,
    ): void {
        if (! $isPresent) {
            return;
        }

        if (! is_array($evidence) || (array_is_list($evidence) && $evidence !== [])) {
            $this->addError(
                'handoff.evidence.invalid_type',
                'Handoff evidence must be an object.',
                details: ['field' => 'evidence'],
            );

            return;
        }

        $evidence = ArrayData::stringMap($evidence);

        if ($isCompleted) {
            $this->validateCompletedEvidenceStatus($evidence, 'audit');
            $this->validateCompletedEvidenceStatus($evidence, 'tests');
        }

        $feedback = $evidence['feedback'] ?? null;

        if (! is_array($feedback) || ! array_key_exists('unresolved', $feedback)) {
            $this->addError(
                'handoff.evidence.feedback_unresolved_missing',
                'Handoff evidence.feedback.unresolved is required, even when zero.',
                details: ['field' => 'evidence.feedback.unresolved'],
            );
        } elseif (! is_int($feedback['unresolved']) || $feedback['unresolved'] < 0) {
            $this->addError(
                'handoff.evidence.feedback_unresolved_invalid',
                'Handoff evidence.feedback.unresolved must be a non-negative integer.',
                details: [
                    'field' => 'evidence.feedback.unresolved',
                    'value' => $feedback['unresolved'],
                ],
            );
        }

        $this->validateArtifactReferences($evidence, 'evidence', $basePath, $changedFilePaths);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function validateCompletedEvidenceStatus(array $evidence, string $key): void
    {
        $section = $evidence[$key] ?? null;
        $status = is_array($section) ? ($section['status'] ?? null) : null;

        if (! is_string($status) || $status === '') {
            $this->addError(
                "handoff.evidence.{$key}_status_missing",
                "Completed handoffs must include evidence.{$key}.status.",
                details: ['field' => "evidence.{$key}.status"],
            );

            return;
        }

        if ($status === EvidenceStatus::Failed->value) {
            $this->addError(
                "handoff.evidence.{$key}_failed",
                "Completed handoffs cannot reference failed {$key} evidence.",
                details: [
                    'field' => "evidence.{$key}.status",
                    'status' => $status,
                ],
            );
        }
    }

    /**
     * @param  array<string, true>  $changedFilePaths
     */
    private function validateArtifacts(mixed $artifacts, bool $isPresent, string $basePath, array $changedFilePaths): void
    {
        if (! $isPresent) {
            return;
        }

        if (! is_array($artifacts) || (array_is_list($artifacts) && $artifacts !== [])) {
            $this->addError(
                'handoff.artifacts.invalid_type',
                'Handoff artifacts must be an object.',
                details: ['field' => 'artifacts'],
            );

            return;
        }

        foreach ($artifacts as $key => $artifact) {
            $this->validateArtifactTree($artifact, 'artifacts.'.(string) $key, $basePath, $changedFilePaths);
        }
    }

    /**
     * @param  array<string, true>  $changedFilePaths
     */
    private function validateArtifactTree(mixed $node, string $location, string $basePath, array $changedFilePaths): void
    {
        if (! is_array($node)) {
            $this->addError(
                'handoff.artifact.invalid',
                'Artifact leaves must be objects containing kind and path.',
                details: ['location' => $location],
            );

            return;
        }

        if ($this->looksLikeArtifactReference($node) || $this->isTerminalArray($node)) {
            $this->validateArtifactReference($node, $location, $basePath, $changedFilePaths);

            return;
        }

        foreach ($node as $key => $value) {
            $this->validateArtifactTree($value, $location.'.'.(string) $key, $basePath, $changedFilePaths);
        }
    }

    /**
     * @param  array<string, true>  $changedFilePaths
     */
    private function validateArtifactReferences(mixed $node, string $location, string $basePath, array $changedFilePaths): void
    {
        if (! is_array($node)) {
            return;
        }

        if ($this->looksLikeArtifactReference($node)) {
            $this->validateArtifactReference($node, $location, $basePath, $changedFilePaths);

            return;
        }

        foreach ($node as $key => $value) {
            $this->validateArtifactReferences($value, $location.'.'.(string) $key, $basePath, $changedFilePaths);
        }
    }

    /**
     * @param  array<mixed>  $artifact
     * @param  array<string, true>  $changedFilePaths
     */
    private function validateArtifactReference(array $artifact, string $location, string $basePath, array $changedFilePaths): void
    {
        $kind = $artifact['kind'] ?? null;
        $path = $artifact['path'] ?? null;

        if (! is_string($kind) || trim($kind) === '') {
            $this->addError(
                'handoff.artifact.kind_missing',
                'Artifact references must include a non-empty kind.',
                details: ['location' => $location],
            );
        }

        if (! is_string($path) || trim($path) === '') {
            $this->addError(
                'handoff.artifact.path_missing',
                'Artifact references must include a non-empty path.',
                details: ['location' => $location],
            );

            return;
        }

        $normalizedPath = $this->projectRelativePath($path);

        if ($normalizedPath === null) {
            $this->addError(
                'handoff.artifact.path_invalid',
                'Artifact paths must be project-relative and must not contain traversal or .git segments.',
                details: [
                    'location' => $location,
                    'path' => $path,
                ],
            );

            return;
        }

        $isAllowedArtifactPath = str_starts_with($normalizedPath, '.iak/runs/')
            || str_starts_with($normalizedPath, '.iak/feedback/')
            || isset($changedFilePaths[$normalizedPath]);

        if (! $isAllowedArtifactPath) {
            $this->addError(
                'handoff.artifact.path_not_allowed',
                'Artifact paths must live under .iak/runs, .iak/feedback, or reference a changed project file.',
                file: $normalizedPath,
                details: [
                    'location' => $location,
                    'path' => $normalizedPath,
                ],
            );

            return;
        }

        $absolutePath = $this->joinPath($basePath, $normalizedPath);

        if (! file_exists($absolutePath)) {
            $this->addError(
                'handoff.artifact.missing',
                'Referenced artifact path does not exist.',
                file: $normalizedPath,
                details: [
                    'location' => $location,
                    'path' => $normalizedPath,
                ],
            );

            return;
        }

        $baseRealPath = realpath($basePath);
        $artifactRealPath = realpath($absolutePath);

        if ($baseRealPath === false || $artifactRealPath === false || ! $this->pathIsInside($artifactRealPath, $baseRealPath)) {
            $this->addError(
                'handoff.artifact.path_outside_base',
                'Referenced artifact path must resolve under the supplied base path.',
                file: $normalizedPath,
                details: [
                    'location' => $location,
                    'path' => $normalizedPath,
                ],
            );
        }
    }

    private function validateNotes(mixed $notes, bool $isPresent): void
    {
        if (! $isPresent) {
            return;
        }

        if (! is_array($notes) || ! array_is_list($notes)) {
            $this->addError(
                'handoff.notes.invalid_type',
                'Handoff notes must be a list of strings.',
                details: ['field' => 'notes'],
            );

            return;
        }

        foreach ($notes as $index => $note) {
            if (! is_string($note)) {
                $this->addError(
                    'handoff.notes.invalid',
                    'Each handoff note must be a string.',
                    details: ['index' => $index],
                );

                continue;
            }

            if ($this->stringLength($note) > 300) {
                $this->addError(
                    'handoff.notes.too_long',
                    'Each handoff note must be 300 characters or fewer.',
                    details: [
                        'index' => $index,
                        'max' => 300,
                        'actual' => $this->stringLength($note),
                    ],
                );
            }
        }
    }

    private function validateNextActions(mixed $nextActions, bool $isPresent, bool $isCompleted): void
    {
        if (! $isPresent) {
            return;
        }

        if (! is_array($nextActions) || ! array_is_list($nextActions)) {
            $this->addError(
                'handoff.next_actions.invalid_type',
                'Handoff nextActions must be a list.',
                details: ['field' => 'nextActions'],
            );

            return;
        }

        foreach ($nextActions as $index => $nextAction) {
            if (! is_array($nextAction)) {
                $this->addError(
                    'handoff.next_actions.invalid',
                    'Each handoff next action must be an object.',
                    details: ['index' => $index],
                );

                continue;
            }

            $blocking = array_key_exists('blocking', $nextAction) ? $nextAction['blocking'] : null;

            if ($isCompleted && $blocking !== false) {
                $this->addError(
                    'handoff.next_actions.blocking',
                    'Completed handoffs can only include next actions when each one is explicitly non-blocking.',
                    details: [
                        'index' => $index,
                        'blocking' => $blocking,
                    ],
                );
            }
        }
    }

    private function validateErrors(mixed $errors, bool $isPresent, bool $isCompleted): void
    {
        if (! $isPresent) {
            return;
        }

        if (! is_array($errors) || ! array_is_list($errors)) {
            $this->addError(
                'handoff.errors.invalid_type',
                'Handoff errors must be a list.',
                details: ['field' => 'errors'],
            );

            return;
        }

        if ($isCompleted && $errors !== []) {
            $this->addError(
                'handoff.errors.present',
                'Completed handoffs must not include errors.',
                details: ['count' => count($errors)],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractNextActions(mixed $nextActions): array
    {
        if (! is_array($nextActions) || ! array_is_list($nextActions)) {
            return [];
        }

        $items = [];

        foreach ($nextActions as $nextAction) {
            if (is_array($nextAction)) {
                $nextAction = ArrayData::stringMap($nextAction);

                if ($nextAction !== []) {
                    $items[] = $nextAction;
                }
            }
        }

        return $items;
    }

    private function projectRelativePath(mixed $path): ?string
    {
        if (! is_string($path)) {
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

    /**
     * @param  array<mixed>  $node
     */
    private function looksLikeArtifactReference(array $node): bool
    {
        return array_key_exists('kind', $node) || array_key_exists('path', $node);
    }

    /**
     * @param  array<mixed>  $node
     */
    private function isTerminalArray(array $node): bool
    {
        foreach ($node as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        return $node !== [];
    }

    private function normalizeBasePath(string $basePath): string
    {
        return rtrim(str_replace('\\', '/', $basePath), '/');
    }

    private function joinPath(string $basePath, string $relativePath): string
    {
        return $basePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function pathIsInside(string $path, string $basePath): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');

        return $path === $basePath || str_starts_with($path, $basePath.'/');
    }

    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function requiredFieldCode(string $field): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $field));

        return "handoff.{$snake}.required";
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function addError(
        string $code,
        string $message,
        ?string $file = null,
        ?int $line = null,
        array $details = [],
    ): void {
        $this->errors[] = [
            'code' => $code,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'details' => $details,
        ];
    }
}
