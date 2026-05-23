<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use InertiaAgentKit\Enum\HandoffStatus;
use JsonSerializable;
use stdClass;

final readonly class HandoffData implements JsonSerializable
{
    /**
     * @param  list<ChangedFileData>  $changedFiles
     * @param  list<string>  $notes
     * @param  list<NextActionData>  $nextActions
     * @param  list<HandoffErrorData>  $errors
     */
    public function __construct(
        public string $runId,
        public ?string $task,
        public HandoffStatus $status,
        public string $summary,
        public HandoffEvidenceData $evidence,
        public ArtifactReferenceData $handoffArtifact,
        public array $changedFiles = [],
        public array $notes = [],
        public array $nextActions = [],
        public array $errors = [],
        public ?HandoffMetaData $meta = null,
        public string $schema = 'iak.handoff.v1',
        public int $version = 1,
        public string $command = 'iak:handoff',
    ) {}

    /**
     * @return array{
     *     schema: string,
     *     version: int,
     *     command: string,
     *     runId: string,
     *     task: string|null,
     *     status: string,
     *     summary: string,
     *     changedFiles: array<string, list<array{path: string, action: string}>>|stdClass,
     *     evidence: array{
     *         audit: array{status: string|null, artifact: array{kind: string, path: string, schema?: string, status?: string}|null},
     *         tests: array{status: string|null, artifact: array{kind: string, path: string, schema?: string, status?: string}|null},
     *         verify: array{artifact: array{kind: string, path: string, schema?: string, status?: string}|null},
     *         feedback: array{unresolved: int|null}
     *     },
     *     artifacts: array{handoff: array{kind: string, path: string, schema?: string, status?: string}},
     *     notes: list<string>,
     *     nextActions: list<array{type: string, summary: string, command?: string, blocking?: bool}>,
     *     errors: list<array{code: string, message: string, file: string|null, line: int|null, details: array<string, mixed>}>,
     *     meta?: array{
     *         createdAt: string,
     *         package: string,
     *         iakVersion: string,
     *         mode?: string,
     *         source?: string,
     *         requested?: array{status?: string, changedFile?: list<string>, hasGroupedChangedFiles?: bool}
     *     }
     * }
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'schema' => $this->schema,
            'version' => $this->version,
            'command' => $this->command,
            'runId' => $this->runId,
            'task' => $this->task,
            'status' => $this->status->value,
            'summary' => $this->summary,
            'changedFiles' => $this->serializeChangedFiles(),
            'evidence' => $this->evidence->jsonSerialize(),
            'artifacts' => [
                'handoff' => $this->handoffArtifact->jsonSerialize(),
            ],
            'notes' => $this->notes,
            'nextActions' => $this->serializeNextActions(),
            'errors' => $this->serializeErrors(),
        ];

        if ($this->meta !== null) {
            $payload['meta'] = $this->meta->jsonSerialize();
        }

        return $payload;
    }

    /**
     * @return array<string, list<array{path: string, action: string}>>|stdClass
     */
    private function serializeChangedFiles(): array|stdClass
    {
        /** @var array<string, list<array{path: string, action: string}>> $grouped */
        $grouped = [];

        foreach ($this->changedFiles as $changedFile) {
            $grouped[$changedFile->role->value][] = $changedFile->jsonSerialize();
        }

        return $grouped === [] ? (object) [] : $grouped;
    }

    /**
     * @return list<array{type: string, summary: string, command?: string, blocking?: bool}>
     */
    private function serializeNextActions(): array
    {
        $nextActions = [];

        foreach ($this->nextActions as $nextAction) {
            $nextActions[] = $nextAction->jsonSerialize();
        }

        return $nextActions;
    }

    /**
     * @return list<array{code: string, message: string, file: string|null, line: int|null, details: array<string, mixed>}>
     */
    private function serializeErrors(): array
    {
        $errors = [];

        foreach ($this->errors as $error) {
            $errors[] = $error->jsonSerialize();
        }

        return $errors;
    }
}
