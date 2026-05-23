<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Support\ArrayData;
use InertiaAgentKit\Support\ProjectPaths;
use JsonException;

final readonly class ReadJsonArtifact
{
    public function __construct(
        private ProjectPaths $paths,
        private ResolveHandoffPath $resolveHandoffPath,
    ) {}

    /**
     * @return array{path: string|null, payload: array<string, mixed>|null, error: HandoffErrorData|null}
     */
    public function handle(string $path, string $source = 'artifact'): array
    {
        $source = trim($source);
        $source = $source === '' ? 'artifact' : $source;
        $normalized = $this->resolveHandoffPath->handle(path: $path);

        if ($normalized === null) {
            return [
                'path' => null,
                'payload' => null,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.path_invalid",
                    message: 'Path must be project-relative and must not contain traversal or .git segments.',
                    file: $path,
                ),
            ];
        }

        $absolute = $this->paths->basePath($normalized);

        if (! is_file($absolute)) {
            return [
                'path' => $normalized,
                'payload' => null,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.missing",
                    message: "JSON artifact [{$normalized}] was not found.",
                    file: $normalized,
                ),
            ];
        }

        $contents = file_get_contents($absolute);

        if ($contents === false) {
            return [
                'path' => $normalized,
                'payload' => null,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.read_failed",
                    message: "JSON artifact [{$normalized}] could not be read.",
                    file: $normalized,
                ),
            ];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [
                'path' => $normalized,
                'payload' => null,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.invalid_json",
                    message: "JSON artifact [{$normalized}] is not valid JSON.",
                    file: $normalized,
                    details: ['jsonError' => $exception->getMessage()],
                ),
            ];
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return [
                'path' => $normalized,
                'payload' => null,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.invalid_payload",
                    message: "JSON artifact [{$normalized}] must contain a JSON object.",
                    file: $normalized,
                ),
            ];
        }

        return [
            'path' => $normalized,
            'payload' => ArrayData::stringMap($decoded),
            'error' => null,
        ];
    }
}
