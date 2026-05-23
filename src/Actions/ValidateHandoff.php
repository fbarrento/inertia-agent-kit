<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use Illuminate\Console\Command;
use InertiaAgentKit\Data\HandoffCommandResultData;
use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Support\ArrayData;
use InertiaAgentKit\Support\ProjectPaths;

final readonly class ValidateHandoff
{
    public function __construct(
        private ReadJsonArtifact $readJsonArtifact,
        private ValidateHandoffPayload $validateHandoffPayload,
        private ProjectPaths $paths,
        private ResolveHandoffPath $resolveHandoffPath,
        private NormalizeHandoffText $normalizeText,
        private BuildHandoffCommandPayloadData $buildPayloadData,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function handle(?string $path, ?string $runId, array $config = []): HandoffCommandResultData
    {
        $config = ArrayData::stringMap($config);
        $schema = ArrayData::stringAt($config, ['json_schemas', 'handoff'], 'iak.handoff.v1');
        /** @var array<string, mixed> $meta */
        $meta = [
            'createdAt' => gmdate('c'),
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => ArrayData::stringAt($config, ['iakVersion'], '0.1.0'),
        ];

        $runId = $this->normalizeText->handle($runId);
        $artifactPath = $this->normalizeText->handle($path);

        if ($artifactPath === null && $runId !== null) {
            $artifactPath = $this->resolveHandoffPath->handle(runId: $runId);
        }

        if ($artifactPath === null) {
            return new HandoffCommandResultData(
                payload: $this->buildPayloadData->handle([
                    'schema' => $schema,
                    'command' => 'iak:handoff',
                    'action' => 'validate',
                    'status' => 'invalid',
                    'summary' => 'Provide a handoff path or --run-id for validation.',
                    'runId' => $runId,
                    'version' => null,
                    'valid' => false,
                    'errors' => [[
                        'code' => 'handoff.path.required',
                        'message' => 'Provide a handoff path or --run-id for validation.',
                        'file' => null,
                        'line' => null,
                        'details' => [],
                    ]],
                    'meta' => [
                        'source' => 'missing_path',
                    ],
                ], $meta, $schema),
                status: Command::INVALID,
            );
        }

        $readResult = $this->readJsonArtifact->handle($artifactPath, 'handoff');

        if ($readResult['error'] instanceof HandoffErrorData) {
            /** @var array{code: string, message: string, file: string|null, line: int|null, details: mixed} $error */
            $error = $readResult['error']->jsonSerialize();
            $payloadErrors = [
                [
                    'code' => (string) $error['code'],
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => null,
                    'details' => is_array($error['details']) ? $error['details'] : [],
                ],
            ];

            return new HandoffCommandResultData(
                payload: $this->buildPayloadData->handle([
                    'schema' => $schema,
                    'command' => 'iak:handoff',
                    'action' => 'validate',
                    'status' => 'invalid',
                    'summary' => 'Handoff validation failed.',
                    'version' => null,
                    'runId' => $runId,
                    'path' => $artifactPath,
                    'valid' => false,
                    'errors' => $payloadErrors,
                    'nextActions' => [],
                    'meta' => [
                        'source' => 'read_failed',
                    ],
                ], $meta, $schema),
                status: Command::INVALID,
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($readResult['payload'] ?? null) ? $readResult['payload'] : [];
        $validation = $this->validateHandoffPayload->handle($payload, $this->paths->basePath(''));
        /** @var array<string, mixed> $result */
        $result = $validation->jsonSerialize();
        /** @var list<array<string, mixed>> $validationErrors */
        $validationErrors = array_values(is_array($result['errors'] ?? null) ? $result['errors'] : []);
        /** @var list<array<string, mixed>> $validationNextActions */
        $validationNextActions = array_values(is_array($result['nextActions'] ?? null) ? $result['nextActions'] : []);
        $resolvedRunId = is_string($payload['runId'] ?? null) && trim((string) $payload['runId']) !== ''
            ? trim((string) $payload['runId'])
            : $runId;
        $isValid = is_bool($result['valid'] ?? null) ? $result['valid'] : false;

        return new HandoffCommandResultData(
            payload: $this->buildPayloadData->handle([
                'schema' => is_string($payload['schema'] ?? null) ? trim((string) $payload['schema']) : $schema,
                'command' => 'iak:handoff',
                'action' => 'validate',
                'status' => $isValid ? 'valid' : 'invalid',
                'summary' => $isValid ? 'Handoff validation passed.' : 'Handoff validation failed.',
                'version' => null,
                'runId' => $resolvedRunId,
                'path' => $artifactPath,
                'valid' => $isValid,
                'errors' => $validationErrors,
                'nextActions' => $validationNextActions,
                'meta' => [
                    'source' => 'validator',
                ],
            ], $meta, $schema),
            status: $isValid ? Command::SUCCESS : Command::INVALID,
        );
    }
}
