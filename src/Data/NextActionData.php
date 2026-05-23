<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use JsonSerializable;

final readonly class NextActionData implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string $summary,
        public ?string $command = null,
        public ?bool $blocking = null,
    ) {}

    /**
     * @return array{type: string, summary: string, command?: string, blocking?: bool}
     */
    public function jsonSerialize(): array
    {
        $action = [
            'type' => $this->type,
            'summary' => $this->summary,
        ];

        if ($this->command !== null) {
            $action['command'] = $this->command;
        }

        if ($this->blocking !== null) {
            $action['blocking'] = $this->blocking;
        }

        return $action;
    }
}
