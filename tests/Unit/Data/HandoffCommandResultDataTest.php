<?php

declare(strict_types=1);

use InertiaAgentKit\Data\HandoffCommandPayloadData;
use InertiaAgentKit\Data\HandoffCommandResultData;

test('serializes command result payload and status as array data', function (): void {
    $payload = new HandoffCommandPayloadData(
        schema: 'iak.handoff.v1',
        command: 'iak:handoff',
        action: 'validate',
        status: 'valid',
        summary: 'Handoff validation passed.',
        runId: 'run_command_result',
        valid: true,
    );

    $result = new HandoffCommandResultData(
        payload: $payload,
        status: 0,
    );

    expect($result->toArray())->toMatchArray([
        'payload' => [
            'schema' => 'iak.handoff.v1',
            'command' => 'iak:handoff',
            'action' => 'validate',
            'status' => 'valid',
            'summary' => 'Handoff validation passed.',
            'runId' => 'run_command_result',
            'valid' => true,
            'version' => 1,
            'errors' => [],
            'nextActions' => [],
            'notes' => [],
        ],
        'status' => 0,
    ])->and($result->jsonSerialize())->toMatchArray($result->toArray());
});
