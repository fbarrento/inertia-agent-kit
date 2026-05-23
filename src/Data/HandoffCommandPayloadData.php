<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use Illuminate\Contracts\Support\Arrayable;
use InertiaAgentKit\Support\ArrayData;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class HandoffCommandPayloadData implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int|string, mixed>|object|null  $changedFiles
     * @param  array<string|int, mixed>  $evidence
     * @param  array<string|int, mixed>  $artifacts
     * @param  list<string>  $notes
     * @param  array<int, array<string, mixed>|JsonSerializable>  $nextActions
     * @param  array<int, array<string, mixed>|JsonSerializable>  $errors
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $schema,
        public string $command,
        public string $action,
        public string $status,
        public string $summary,
        public ?string $runId = null,
        public string|int|null $version = 1,
        public ?string $task = null,
        public array|object|null $changedFiles = null,
        public array $evidence = [],
        public array $artifacts = [],
        public array $notes = [],
        public array $nextActions = [],
        public array $errors = [],
        public ?array $meta = null,
        public ?string $path = null,
        public ?bool $valid = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'schema' => $this->schema,
            'command' => $this->command,
            'action' => $this->action,
            'status' => $this->status,
            'summary' => $this->summary,
        ];

        if ($this->version !== null) {
            $payload['version'] = $this->version;
        }

        if ($this->runId !== null && $this->runId !== '') {
            $payload['runId'] = $this->runId;
        }

        if ($this->path !== null) {
            $payload['path'] = $this->path;
        }

        if ($this->valid !== null) {
            $payload['valid'] = $this->valid;
        }

        if ($this->task !== null) {
            $payload['task'] = $this->task;
        }

        if ($this->changedFiles !== null) {
            $payload['changedFiles'] = $this->changedFiles;
        }

        if ($this->evidence !== []) {
            $payload['evidence'] = $this->evidence;
        }

        if ($this->artifacts !== []) {
            $payload['artifacts'] = $this->artifacts;
        }

        $payload['notes'] = $this->notes;

        $payload['errors'] = $this->serializePayloadItems($this->errors);
        $payload['nextActions'] = $this->serializePayloadItems($this->nextActions);

        if ($this->meta !== null) {
            $payload['meta'] = $this->meta;
        }

        return $payload;
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function serializePayloadItems(array $items): array
    {
        /** @var list<array<string, mixed>> $serialized */
        $serialized = [];

        foreach ($items as $item) {
            if ($item instanceof JsonSerializable) {
                $payload = $item->jsonSerialize();

                if (is_array($payload)) {
                    $serialized[] = ArrayData::stringMap($payload);
                }

                continue;
            }

            if (is_array($item)) {
                $serialized[] = ArrayData::stringMap($item);
            }
        }

        return $serialized;
    }
}
