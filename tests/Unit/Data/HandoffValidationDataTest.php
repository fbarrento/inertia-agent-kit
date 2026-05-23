<?php

declare(strict_types=1);

use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Data\HandoffValidationData;
use InertiaAgentKit\Data\NextActionData;
use InertiaAgentKit\Enum\HandoffStatus;

test('serializes handoff validation results with errors and next actions', function (): void {
    $result = new HandoffValidationData(
        valid: false,
        status: HandoffStatus::Invalid,
        errors: [
            new HandoffErrorData(
                code: 'handoff.next_actions.blocking',
                message: 'Completed handoffs can only include non-blocking next actions.',
                details: ['index' => 0],
            ),
        ],
        nextActions: [
            new NextActionData(
                type: 'fix',
                summary: 'Run another implementation pass.',
                command: 'composer test',
                blocking: true,
            ),
        ],
    );

    expect($result->jsonSerialize())->toBe([
        'valid' => false,
        'status' => 'invalid',
        'errors' => [[
            'code' => 'handoff.next_actions.blocking',
            'message' => 'Completed handoffs can only include non-blocking next actions.',
            'file' => null,
            'line' => null,
            'details' => [
                'index' => 0,
            ],
        ]],
        'nextActions' => [[
            'type' => 'fix',
            'summary' => 'Run another implementation pass.',
            'command' => 'composer test',
            'blocking' => true,
        ]],
    ]);
});

test('serializes empty validation details', function (): void {
    $result = new HandoffValidationData(
        valid: true,
        status: HandoffStatus::Valid,
    );

    expect($result->jsonSerialize())->toBe([
        'valid' => true,
        'status' => 'valid',
        'errors' => [],
        'nextActions' => [],
    ]);
});
