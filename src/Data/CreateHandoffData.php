<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use InertiaAgentKit\Enum\HandoffStatus;
use JsonSerializable;

final readonly class CreateHandoffData implements JsonSerializable
{
    /**
     * @param  list<ChangedFileData>  $changedFiles
     * @param  list<string>  $notes
     * @param  list<NextActionData>  $nextActions
     */
    public function __construct(
        public ?string $task = null,
        public HandoffStatus $status = HandoffStatus::Completed,
        public string $summary = '',
        public ?string $runId = null,
        public string $command = 'iak:handoff',
        public array $changedFiles = [],
        public ?ArtifactReferenceData $auditArtifact = null,
        public ?ArtifactReferenceData $testsArtifact = null,
        public ?ArtifactReferenceData $verifyArtifact = null,
        public ?int $feedbackUnresolved = null,
        public array $notes = [],
        public array $nextActions = [],
    ) {}

    /**
     * @return array{
     *     command: string,
     *     status: string,
     *     summary: string,
     *     runId?: string,
     *     task?: string,
     *     changedFiles?: array<string, list<array{path: string, action: string}>>,
     *     audit?: string,
     *     tests?: string,
     *     verify?: string,
     *     feedbackUnresolved?: int,
     *     notes?: list<string>,
     *     nextActions?: list<array{type: string, summary: string, command?: string, blocking?: bool}>
     * }
     */
    public function jsonSerialize(): array
    {
        $request = [
            'command' => $this->command,
            'status' => $this->status->value,
            'summary' => $this->summary,
        ];

        if ($this->runId !== null) {
            $request['runId'] = $this->runId;
        }

        if ($this->task !== null) {
            $request['task'] = $this->task;
        }

        $changedFiles = $this->serializeChangedFiles();

        if ($changedFiles !== []) {
            $request['changedFiles'] = $changedFiles;
        }

        if ($this->auditArtifact !== null) {
            $request['audit'] = $this->auditArtifact->path;
        }

        if ($this->testsArtifact !== null) {
            $request['tests'] = $this->testsArtifact->path;
        }

        if ($this->verifyArtifact !== null) {
            $request['verify'] = $this->verifyArtifact->path;
        }

        if ($this->feedbackUnresolved !== null) {
            $request['feedbackUnresolved'] = $this->feedbackUnresolved;
        }

        if ($this->notes !== []) {
            $request['notes'] = $this->notes;
        }

        $nextActions = $this->serializeNextActions();

        if ($nextActions !== []) {
            $request['nextActions'] = $nextActions;
        }

        return $request;
    }

    /**
     * @return array<string, list<array{path: string, action: string}>>
     */
    private function serializeChangedFiles(): array
    {
        /** @var array<string, list<array{path: string, action: string}>> $grouped */
        $grouped = [];

        foreach ($this->changedFiles as $changedFile) {
            $grouped[$changedFile->role->value][] = $changedFile->jsonSerialize();
        }

        return $grouped;
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
}
