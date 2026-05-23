<?php

declare(strict_types=1);

use InertiaAgentKit\Data\HandoffCommandInputData;

test('stores handoff command input with explicit values', function (): void {
    $payload = ['status' => 'completed'];
    $input = new HandoffCommandInputData(
        action: 'create',
        payload: $payload,
        path: '.iak/runs/run_unit_input/handoff.json',
        runId: 'run_unit_input',
    );

    expect($input->action)->toBe('create')
        ->and($input->payload)->toBe($payload)
        ->and($input->path)->toBe('.iak/runs/run_unit_input/handoff.json')
        ->and($input->runId)->toBe('run_unit_input');
});

test('defaults optional handoff command fields', function (): void {
    $input = new HandoffCommandInputData(action: 'validate');

    expect($input->action)->toBe('validate')
        ->and($input->payload)->toBe([])
        ->and($input->path)->toBeNull()
        ->and($input->runId)->toBeNull();
});
