<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use InertiaAgentKit\Enum\EvidenceStatus;
use JsonSerializable;

final readonly class HandoffEvidenceData implements JsonSerializable
{
    public function __construct(
        public ?ArtifactReferenceData $auditArtifact = null,
        public ?EvidenceStatus $auditStatus = null,
        public ?ArtifactReferenceData $testsArtifact = null,
        public ?EvidenceStatus $testsStatus = null,
        public ?ArtifactReferenceData $verifyArtifact = null,
        public ?int $feedbackUnresolved = null,
    ) {}

    /**
     * @return array{
     *     audit: array{status: string|null, artifact: array{kind: string, path: string, schema?: string, status?: string}|null},
     *     tests: array{status: string|null, artifact: array{kind: string, path: string, schema?: string, status?: string}|null},
     *     verify: array{artifact: array{kind: string, path: string, schema?: string, status?: string}|null},
     *     feedback: array{unresolved: int|null}
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'audit' => [
                'status' => $this->auditStatus?->value,
                'artifact' => $this->serializeArtifact($this->auditArtifact),
            ],
            'tests' => [
                'status' => $this->testsStatus?->value,
                'artifact' => $this->serializeArtifact($this->testsArtifact),
            ],
            'verify' => [
                'artifact' => $this->serializeArtifact($this->verifyArtifact),
            ],
            'feedback' => [
                'unresolved' => $this->feedbackUnresolved,
            ],
        ];
    }

    /**
     * @return array{kind: string, path: string, schema?: string, status?: string}|null
     */
    private function serializeArtifact(?ArtifactReferenceData $artifact): ?array
    {
        return $artifact?->jsonSerialize();
    }
}
