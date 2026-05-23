<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use InertiaAgentKit\Data\HandoffCommandPayloadData;
use InertiaAgentKit\Support\ArrayData;
use JsonSerializable;

final readonly class BuildHandoffCommandPayloadData
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    public function handle(array $payload, array $meta, string $schema): HandoffCommandPayloadData
    {
        $payload = ArrayData::stringMap($payload);

        $runId = is_string($payload['runId'] ?? null) ? trim((string) $payload['runId']) : null;
        $runId = $runId === '' ? null : $runId;

        $payloadMeta = is_array($payload['meta'] ?? null) ? ArrayData::stringMap($payload['meta']) : [];
        $path = null;

        if (array_key_exists('path', $payload) && is_string($payload['path'])) {
            $path = trim((string) $payload['path']);
            $path = $path === '' ? null : $path;
        }

        $changedFiles = null;
        if (is_array($payload['changedFiles'] ?? null) || is_object($payload['changedFiles'] ?? null)) {
            $changedFiles = $payload['changedFiles'];
        }
        /** @var array<int|string, mixed>|object|null $normalizedChangedFiles */
        $normalizedChangedFiles = is_array($changedFiles) ? ArrayData::stringMap($changedFiles) : $changedFiles;

        $notes = [];
        if (is_array($payload['notes'] ?? null)) {
            foreach ($payload['notes'] as $note) {
                if (is_string($note) && trim($note) !== '') {
                    $notes[] = $note;
                }
            }
        }

        /** @var list<array<string, mixed>> $nextActions */
        $nextActions = [];
        if (is_array($payload['nextActions'] ?? null)) {
            foreach ($payload['nextActions'] as $item) {
                if ($item instanceof JsonSerializable) {
                    $itemPayload = $item->jsonSerialize();

                    if (is_array($itemPayload)) {
                        /** @var array<string, mixed> $normalizedItem */
                        $normalizedItem = ArrayData::stringMap($itemPayload);
                        $nextActions[] = $normalizedItem;
                    }

                    continue;
                }

                if (is_array($item)) {
                    /** @var array<string, mixed> $normalizedItem */
                    $normalizedItem = ArrayData::stringMap($item);
                    $nextActions[] = $normalizedItem;
                }
            }
        }

        /** @var list<array<string, mixed>> $errors */
        $errors = [];
        if (is_array($payload['errors'] ?? null)) {
            foreach ($payload['errors'] as $item) {
                if ($item instanceof JsonSerializable) {
                    $itemPayload = $item->jsonSerialize();

                    if (is_array($itemPayload)) {
                        /** @var array<string, mixed> $normalizedItem */
                        $normalizedItem = ArrayData::stringMap($itemPayload);
                        $errors[] = $normalizedItem;
                    }

                    continue;
                }

                if (is_array($item)) {
                    /** @var array<string, mixed> $normalizedItem */
                    $normalizedItem = ArrayData::stringMap($item);
                    $errors[] = $normalizedItem;
                }
            }
        }

        $version = array_key_exists('version', $payload) ? $payload['version'] : 1;

        if (is_string($version)) {
            $version = trim($version);

            if ($version === '') {
                $version = null;
            } elseif (ctype_digit($version)) {
                $version = (int) $version;
            }
        }

        if (! is_int($version) && ! is_string($version)) {
            $version = is_null($version) ? null : 1;
        }

        return new HandoffCommandPayloadData(
            schema: is_string($payload['schema'] ?? null) ? trim((string) $payload['schema']) : $schema,
            command: is_string($payload['command'] ?? null) ? trim((string) $payload['command']) : 'iak:handoff',
            action: is_string($payload['action'] ?? null) ? trim((string) $payload['action']) : 'create',
            status: is_string($payload['status'] ?? null) ? trim((string) $payload['status']) : 'blocked',
            summary: is_string($payload['summary'] ?? null) ? (string) $payload['summary'] : '',
            runId: $runId,
            version: $version,
            task: is_string($payload['task'] ?? null) ? (string) $payload['task'] : null,
            changedFiles: $normalizedChangedFiles,
            evidence: is_array($payload['evidence'] ?? null) ? ArrayData::stringMap($payload['evidence']) : [],
            artifacts: is_array($payload['artifacts'] ?? null) ? ArrayData::stringMap($payload['artifacts']) : [],
            notes: $notes,
            nextActions: $nextActions,
            errors: $errors,
            meta: [
                ...ArrayData::stringMap($meta),
                ...$payloadMeta,
            ],
            path: $path,
            valid: is_bool($payload['valid'] ?? null) ? $payload['valid'] : null,
        );
    }
}
