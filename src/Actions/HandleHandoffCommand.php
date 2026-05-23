<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use Illuminate\Console\Command;
use InertiaAgentKit\Data\HandoffCommandInputData;
use InertiaAgentKit\Data\HandoffCommandPayloadData;
use InertiaAgentKit\Data\HandoffCommandResultData;
use InertiaAgentKit\Support\ArrayData;

final readonly class HandleHandoffCommand
{
    public function __construct(
        private CreateHandoff $createHandoff,
        private ValidateHandoff $validateHandoff,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function handle(HandoffCommandInputData $input, array $config = []): HandoffCommandResultData
    {
        $config = ArrayData::stringMap($config);
        $action = strtolower(trim($input->action));
        $action = $action === '' ? 'create' : $action;

        return match ($action) {
            'create' => $this->createHandoff->handle($input->payload, $config),
            'validate' => $this->validateHandoff->handle($input->path, $input->runId, $config),
            default => new HandoffCommandResultData(
                payload: new HandoffCommandPayloadData(
                    schema: ArrayData::stringAt($config, ['json_schemas', 'handoff'], 'iak.handoff.v1'),
                    command: 'iak:handoff',
                    action: $action,
                    status: 'blocked',
                    summary: 'Handoff action must be create or validate.',
                    runId: $input->runId,
                    version: 1,
                    changedFiles: (object) [],
                    evidence: [],
                    artifacts: [],
                    notes: [],
                    errors: [[
                        'code' => 'handoff.action.invalid',
                        'message' => 'Handoff action must be create or validate.',
                        'file' => null,
                        'line' => null,
                        'details' => [],
                    ]],
                    meta: [
                        'createdAt' => gmdate('c'),
                        'package' => 'fbarrento/inertia-agent-kit',
                        'iakVersion' => ArrayData::stringAt($config, ['iakVersion'], '0.1.0'),
                    ],
                ),
                status: Command::INVALID,
            ),
        };
    }
}
