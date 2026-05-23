<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use JsonSerializable;

final readonly class HandoffErrorData implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public ?string $file = null,
        public ?int $line = null,
        public array $details = [],
    ) {}

    /**
     * @return array{code: string, message: string, file: string|null, line: int|null, details: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'details' => $this->details,
        ];
    }
}
