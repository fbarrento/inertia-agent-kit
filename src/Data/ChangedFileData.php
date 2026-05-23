<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use InertiaAgentKit\Enum\ChangedFileAction;
use InertiaAgentKit\Enum\ChangedFileRole;
use JsonSerializable;

final readonly class ChangedFileData implements JsonSerializable
{
    public function __construct(
        public ChangedFileRole $role,
        public ChangedFileAction $action,
        public string $path,
    ) {}

    /**
     * @return array{path: string, action: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'path' => $this->path,
            'action' => $this->action->value,
        ];
    }
}
