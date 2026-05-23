<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class HandoffCommandResultData implements Arrayable, JsonSerializable
{
    public function __construct(
        public HandoffCommandPayloadData $payload,
        public int $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payload' => $this->payload->jsonSerialize(),
            'status' => $this->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
