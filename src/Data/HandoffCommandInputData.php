<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

final readonly class HandoffCommandInputData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $action,
        public array $payload = [],
        public ?string $path = null,
        public ?string $runId = null,
    ) {}
}
