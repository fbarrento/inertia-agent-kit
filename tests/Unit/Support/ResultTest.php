<?php

declare(strict_types=1);

use InertiaAgentKit\Support\Result;

test('builds an ok response with empty data as object and generated metadata', function (): void {
    $result = Result::ok('iak.ok.v1');

    expect($result['schema'])->toBe('iak.ok.v1')
        ->and($result['status'])->toBe('ok')
        ->and($result['data'])->toBeInstanceOf(stdClass::class)
        ->and($result['meta'])->toHaveKey('generated_at');
});

test('merges custom data into pending response', function (): void {
    $result = Result::pending('iak.pending.v1', 'Still processing', ['step' => 1]);

    expect($result['status'])->toBe('pending')
        ->and($result['data'])->toMatchArray([
            'step' => 1,
            'message' => 'Still processing',
        ]);
});

test('serializes error responses with object context and defaults', function (): void {
    $result = Result::error('iak.error.v1', 'E000', 'Failed to run');

    expect($result['schema'])->toBe('iak.error.v1')
        ->and($result['status'])->toBe('error')
        ->and($result['error'])->toMatchArray([
            'code' => 'E000',
            'message' => 'Failed to run',
            'context' => (object) [],
        ])
        ->and($result['meta'])->toHaveKey('generated_at');
});

test('preserves and normalizes ok metadata values', function (): void {
    $result = Result::ok('iak.ok.v1', ['item' => 1], ['custom' => 'value']);

    expect($result['meta'])->toMatchArray([
        'custom' => 'value',
    ]);
});
