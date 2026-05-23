<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use JsonSerializable;

final readonly class HandoffMetaData implements JsonSerializable
{
    /**
     * @param  list<string>  $requestedChangedFiles
     */
    public function __construct(
        public string $createdAt,
        public string $package,
        public string $iakVersion,
        public ?string $mode = null,
        public ?string $source = null,
        public ?string $requestedStatus = null,
        public array $requestedChangedFiles = [],
        public ?bool $hasGroupedChangedFiles = null,
    ) {}

    /**
     * @return array{
     *     createdAt: string,
     *     package: string,
     *     iakVersion: string,
     *     mode?: string,
     *     source?: string,
     *     requested?: array{status?: string, changedFile?: list<string>, hasGroupedChangedFiles?: bool}
     * }
     */
    public function jsonSerialize(): array
    {
        $meta = [
            'createdAt' => $this->createdAt,
            'package' => $this->package,
            'iakVersion' => $this->iakVersion,
        ];

        if ($this->mode !== null) {
            $meta['mode'] = $this->mode;
        }

        if ($this->source !== null) {
            $meta['source'] = $this->source;
        }

        $requested = [];

        if ($this->requestedStatus !== null) {
            $requested['status'] = $this->requestedStatus;
        }

        if ($this->requestedChangedFiles !== []) {
            $requested['changedFile'] = $this->requestedChangedFiles;
        }

        if ($this->hasGroupedChangedFiles !== null) {
            $requested['hasGroupedChangedFiles'] = $this->hasGroupedChangedFiles;
        }

        if ($requested !== []) {
            $meta['requested'] = $requested;
        }

        return $meta;
    }
}
