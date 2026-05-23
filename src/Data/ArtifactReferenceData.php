<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use InertiaAgentKit\Enum\ArtifactKind;
use JsonSerializable;

final readonly class ArtifactReferenceData implements JsonSerializable
{
    public function __construct(
        public ArtifactKind $kind,
        public string $path,
        public ?string $schema = null,
        public ?string $status = null,
    ) {}

    /**
     * @return array{kind: string, path: string, schema?: string, status?: string}
     */
    public function jsonSerialize(): array
    {
        $artifact = [
            'kind' => $this->kind->value,
            'path' => $this->path,
        ];

        if ($this->schema !== null) {
            $artifact['schema'] = $this->schema;
        }

        if ($this->status !== null) {
            $artifact['status'] = $this->status;
        }

        return $artifact;
    }
}
