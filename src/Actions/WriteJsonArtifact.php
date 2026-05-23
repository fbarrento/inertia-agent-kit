<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Support\ArrayData;
use InertiaAgentKit\Support\ProjectPaths;
use JsonException;
use JsonSerializable;
use RuntimeException;
use Throwable;

final readonly class WriteJsonArtifact
{
    public function __construct(
        private ProjectPaths $paths,
        private ResolveHandoffPath $resolveHandoffPath,
    ) {}

    /**
     * @param  array<string, mixed>|JsonSerializable  $payload
     * @return array{path: string|null, error: HandoffErrorData|null}
     */
    public function handle(
        string $path,
        array|JsonSerializable $payload,
        string $source = 'artifact',
        bool $overwrite = true,
    ): array {
        $source = trim($source);
        $source = $source === '' ? 'artifact' : $source;
        $normalized = $this->resolveHandoffPath->handle(path: $path);

        if ($normalized === null) {
            return [
                'path' => null,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.path_invalid",
                    message: 'Path must be project-relative and must not contain traversal or .git segments.',
                    file: $path,
                ),
            ];
        }

        $serialized = $payload instanceof JsonSerializable ? $payload->jsonSerialize() : $payload;

        if (! is_array($serialized) || array_is_list($serialized)) {
            return [
                'path' => $normalized,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.invalid_payload",
                    message: "JSON artifact [{$normalized}] must contain a JSON object.",
                    file: $normalized,
                ),
            ];
        }

        try {
            $absolute = $this->paths->basePath($normalized);

            if (! $overwrite && is_file($absolute)) {
                throw new RuntimeException("File already exists [{$normalized}].");
            }

            $directory = dirname($absolute);

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("Unable to create directory [{$directory}].");
            }

            $contents = json_encode(
                ArrayData::stringMap($serialized),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ).PHP_EOL;

            if (file_put_contents($absolute, $contents) === false) {
                throw new RuntimeException("Unable to write file [{$normalized}].");
            }
        } catch (JsonException $exception) {
            return [
                'path' => $normalized,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.json_encode_failed",
                    message: "JSON artifact [{$normalized}] could not be encoded.",
                    file: $normalized,
                    details: ['jsonError' => $exception->getMessage()],
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'path' => $normalized,
                'error' => new HandoffErrorData(
                    code: "handoff.{$source}.write_failed",
                    message: 'Unable to write handoff artifact.',
                    file: $normalized,
                    details: [
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ],
                ),
            ];
        }

        return [
            'path' => $normalized,
            'error' => null,
        ];
    }
}
