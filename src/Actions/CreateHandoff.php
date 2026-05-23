<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use Illuminate\Console\Command;
use InertiaAgentKit\Data\HandoffCommandResultData;
use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Support\ArrayData;
use InertiaAgentKit\Support\HandoffPathDefaults;

final readonly class CreateHandoff
{
    public function __construct(
        private ReadJsonArtifact $readJsonArtifact,
        private WriteJsonArtifact $writeJsonArtifact,
        private ResolveHandoffPath $resolveHandoffPath,
        private NormalizeHandoffText $normalizeText,
        private NormalizeHandoffTextList $normalizeTextList,
        private BuildHandoffCommandPayloadData $buildPayloadData,
        private BuildHandoffPayload|BuildHandoffPayloadBuilder|null $buildHandoffPayload = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $config
     */
    public function handle(array $input, array $config = []): HandoffCommandResultData
    {
        $config = ArrayData::stringMap($config);
        $schema = ArrayData::stringAt($config, ['json_schemas', 'handoff'], 'iak.handoff.v1');
        /** @var array<string, mixed> $meta */
        $meta = [
            'createdAt' => gmdate('c'),
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => ArrayData::stringAt($config, ['iakVersion'], '0.1.0'),
        ];

        $runId = $this->normalizeText->handle($input['runId'] ?? null);
        $status = $this->normalizeText->handle($input['status'] ?? null, 'completed') ?? 'completed';

        $createInput = [
            'command' => $this->normalizeText->handle($input['command'] ?? 'iak:handoff', 'iak:handoff'),
            'runId' => $runId,
            'task' => $this->normalizeText->handle($input['task'] ?? null),
            'status' => strtolower($status),
            'summary' => $this->normalizeText->handle($input['summary'] ?? null) ?? '',
            'changedFile' => $this->normalizeTextList->handle($input['changedFile'] ?? null),
            'audit' => $this->normalizeText->handle($input['audit'] ?? null),
            'tests' => $this->normalizeText->handle($input['tests'] ?? null),
            'verify' => $this->normalizeText->handle($input['verify'] ?? null),
            'feedbackUnresolved' => $this->normalizeText->handle($input['feedbackUnresolved'] ?? null),
            'note' => $this->normalizeTextList->handle($input['note'] ?? null),
            'nextAction' => $this->normalizeTextList->handle($input['nextAction'] ?? null),
        ];

        $changedFilesPath = $this->normalizeText->handle($input['changedFilesPath'] ?? null);

        if ($changedFilesPath !== null) {
            $readResult = $this->readJsonArtifact->handle($changedFilesPath, 'changed_files');

            if ($readResult['error'] instanceof HandoffErrorData) {
                /** @var array{code: string, message: string, file: string|null, line: int|null, details: mixed} $error */
                $error = $readResult['error']->jsonSerialize();
                $errorCode = (string) $error['code'];
                $errorDetails = is_array($error['details']) ? $error['details'] : [];
                $resolvedRunId = $runId ?? 'run_not_created';

                return new HandoffCommandResultData(
                    payload: $this->buildPayloadData->handle([
                        'schema' => $schema,
                        'command' => 'iak:handoff',
                        'action' => 'create',
                        'status' => 'blocked',
                        'summary' => 'Handoff create blocked: '.((string) $error['message']),
                        'runId' => $resolvedRunId,
                        'version' => 1,
                        'changedFiles' => (object) [],
                        'artifacts' => [
                            'handoff' => [
                                'kind' => 'json',
                                'path' => HandoffPathDefaults::RUNS.'/'.$resolvedRunId.'/handoff.json',
                                'schema' => $schema,
                                'status' => 'not_written',
                            ],
                        ],
                        'errors' => [[
                            'code' => $errorCode,
                            'message' => $error['message'],
                            'file' => $error['file'] ?? $changedFilesPath,
                            'line' => null,
                            'details' => $errorDetails,
                        ]],
                    ], $meta, $schema),
                    status: Command::INVALID,
                );
            }

            $readPayload = is_array($readResult['payload'] ?? null) ? $readResult['payload'] : [];
            $hasChangedFiles = array_key_exists('changedFiles', $readPayload);
            $changedFiles = $hasChangedFiles ? ($readPayload['changedFiles'] ?? null) : $readPayload;

            if ($hasChangedFiles && ! is_array($changedFiles)) {
                $resolvedRunId = $runId ?? 'run_not_created';

                return new HandoffCommandResultData(
                    payload: $this->buildPayloadData->handle([
                        'schema' => $schema,
                        'command' => 'iak:handoff',
                        'action' => 'create',
                        'status' => 'blocked',
                        'summary' => 'Handoff create blocked: Changed files artifact must contain a grouped changedFiles object.',
                        'runId' => $resolvedRunId,
                        'version' => 1,
                        'changedFiles' => (object) [],
                        'artifacts' => [
                            'handoff' => [
                                'kind' => 'json',
                                'path' => HandoffPathDefaults::RUNS.'/'.$resolvedRunId.'/handoff.json',
                                'schema' => $schema,
                                'status' => 'not_written',
                            ],
                        ],
                        'errors' => [[
                            'code' => 'handoff.changed_files.invalid_payload',
                            'message' => 'Changed files artifact must contain a grouped changedFiles object.',
                            'file' => $readResult['path'],
                            'line' => null,
                            'details' => ['field' => 'changedFiles'],
                        ]],
                    ], $meta, $schema),
                    status: Command::INVALID,
                );
            }

            if (is_array($changedFiles)) {
                $createInput['changedFiles'] = ArrayData::stringMap($changedFiles);
            }
        }

        $buildHandoffPayload = $this->buildHandoffPayload ?? new BuildHandoffPayload;

        $payload = $buildHandoffPayload->handle($createInput, $config);

        $artifacts = ArrayData::stringMap($payload['artifacts'] ?? []);
        $handoffArtifact = ArrayData::stringMap($artifacts['handoff'] ?? []);
        $artifactPath = is_string($handoffArtifact['path'] ?? null)
            ? trim((string) $handoffArtifact['path'])
            : null;
        $runId = is_string($payload['runId'] ?? null)
            ? trim((string) $payload['runId'])
            : 'run_not_created';

        if (! is_string($artifactPath) || $artifactPath === '') {
            $artifactPath = $this->resolveHandoffPath->handle(runId: $runId) ?? HandoffPathDefaults::RUNS.'/'.$runId.'/handoff.json';
        }

        $writePayload = [
            ...$payload,
            'artifacts' => [
                ...$artifacts,
                'handoff' => [
                    'kind' => $handoffArtifact['kind'] ?? 'json',
                    'path' => $artifactPath,
                    'schema' => $handoffArtifact['schema'] ?? $schema,
                    'status' => 'written',
                ],
            ],
        ];

        $writePayloadData = $this->buildPayloadData->handle($writePayload, $meta, $schema);
        $writeResult = $this->writeJsonArtifact->handle($artifactPath, $writePayloadData, 'handoff');

        if ($writeResult['error'] instanceof HandoffErrorData) {
            $writeError = $writeResult['error']->jsonSerialize();
            $errors = ArrayData::stringMapList($payload['errors'] ?? []);
            $errors[] = $writeError;

            return new HandoffCommandResultData(
                payload: $this->buildPayloadData->handle([
                    ...$writePayload,
                    'status' => 'blocked',
                    'summary' => is_string($payload['summary'] ?? null) ? (string) $payload['summary'] : 'Handoff artifact could not be written.',
                    'artifacts' => [
                        ...$artifacts,
                        'handoff' => [
                            'kind' => 'json',
                            'path' => $artifactPath,
                            'schema' => ArrayData::stringAt($payload, ['artifacts', 'handoff', 'schema'], $schema),
                            'status' => 'not_written',
                        ],
                    ],
                    'errors' => $errors,
                ], $meta, $schema),
                status: Command::INVALID,
            );
        }

        $payload = [
            ...$payload,
            'artifacts' => [
                ...$artifacts,
                'handoff' => [
                    'kind' => $handoffArtifact['kind'] ?? 'json',
                    'path' => $artifactPath,
                    'schema' => $handoffArtifact['schema'] ?? $schema,
                    'status' => 'written',
                ],
            ],
        ];

        $status = ($payload['status'] === 'completed' && ArrayData::stringMapList($payload['errors'] ?? []) === [])
            ? Command::SUCCESS
            : Command::INVALID;

        return new HandoffCommandResultData(
            payload: $this->buildPayloadData->handle($payload, $meta, $schema),
            status: $status,
        );
    }
}
