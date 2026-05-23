<?php

declare(strict_types=1);

use InertiaAgentKit\Support\HandoffValidationDataFactory;

beforeEach(function (): void {
    $this->handoffValidationDataFactory = new HandoffValidationDataFactory;
});

test('creates validation data for a valid result', function (): void {
    $result = $this->handoffValidationDataFactory->make([
        'valid' => true,
        'status' => 'valid',
        'errors' => [],
        'nextActions' => [],
    ]);

    expect($result->jsonSerialize())->toBe([
        'valid' => true,
        'status' => 'valid',
        'errors' => [],
        'nextActions' => [],
    ]);
});

test('creates validation data with serialized errors and next actions', function (): void {
    $result = $this->handoffValidationDataFactory->make([
        'valid' => false,
        'status' => 'invalid',
        'errors' => [[
            'code' => 'handoff.next_actions.blocking',
            'message' => 'Completed handoffs can only include non-blocking next actions.',
            'file' => 'resources/js/pages/vehicles/index.tsx',
            'line' => 12,
            'details' => [
                'index' => 0,
                'blocking' => true,
            ],
        ]],
        'nextActions' => [[
            'type' => 'fix',
            'summary' => 'Run another implementation pass.',
            'command' => 'composer test',
            'blocking' => true,
        ]],
    ]);

    expect($result->jsonSerialize())->toBe([
        'valid' => false,
        'status' => 'invalid',
        'errors' => [[
            'code' => 'handoff.next_actions.blocking',
            'message' => 'Completed handoffs can only include non-blocking next actions.',
            'file' => 'resources/js/pages/vehicles/index.tsx',
            'line' => 12,
            'details' => [
                'index' => 0,
                'blocking' => true,
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

test('normalizes unexpected scalar shapes defensively', function (): void {
    $result = $this->handoffValidationDataFactory->make([
        'valid' => false,
        'status' => 'invalid',
        'errors' => [[
            'code' => false,
            'message' => [],
            'file' => false,
            'line' => '12',
            'details' => ['index' => 0],
        ]],
        'nextActions' => [[
            'type' => false,
            'summary' => [],
            'command' => false,
            'blocking' => 'true',
        ]],
    ]);

    expect($result->jsonSerialize())->toBe([
        'valid' => false,
        'status' => 'invalid',
        'errors' => [[
            'code' => '',
            'message' => '',
            'file' => null,
            'line' => null,
            'details' => [
                'index' => 0,
            ],
        ]],
        'nextActions' => [[
            'type' => '',
            'summary' => '',
        ]],
    ]);
});
