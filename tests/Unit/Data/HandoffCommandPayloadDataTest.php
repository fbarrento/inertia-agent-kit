<?php

declare(strict_types=1);

use InertiaAgentKit\Data\HandoffCommandPayloadData;
use InertiaAgentKit\Data\HandoffErrorData;

test('serializes a blocked create payload with full artifact and error boundaries', function (): void {
    $payload = new HandoffCommandPayloadData(
        schema: 'iak.handoff.v1',
        command: 'iak:handoff',
        action: 'create',
        status: 'blocked',
        summary: 'Handoff create blocked: changed files artifact is invalid.',
        runId: 'run_command_payload',
        version: 1,
        changedFiles: (object) [],
        evidence: [],
        artifacts: [
            'handoff' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_command_payload/handoff.json',
                'schema' => 'iak.handoff.v1',
                'status' => 'not_written',
            ],
        ],
        notes: [],
        errors: [
            new HandoffErrorData(
                code: 'handoff.create.failed',
                message: 'Changed files artifact must contain a grouped changedFiles object.',
                file: '.iak/runs/run_command_payload/changed-files.json',
                details: [
                    'field' => 'changedFiles',
                ],
            ),
        ],
        nextActions: [],
        meta: [
            'createdAt' => '2026-05-22T15:00:00+00:00',
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => '0.1.0',
        ],
    );

    expect($payload->jsonSerialize())->toMatchArray([
        'schema' => 'iak.handoff.v1',
        'command' => 'iak:handoff',
        'action' => 'create',
        'status' => 'blocked',
        'summary' => 'Handoff create blocked: changed files artifact is invalid.',
        'version' => 1,
        'runId' => 'run_command_payload',
        'changedFiles' => (object) [],
        'artifacts' => [
            'handoff' => [
                'kind' => 'json',
                'path' => '.iak/runs/run_command_payload/handoff.json',
                'schema' => 'iak.handoff.v1',
                'status' => 'not_written',
            ],
        ],
        'errors' => [[
            'code' => 'handoff.create.failed',
            'message' => 'Changed files artifact must contain a grouped changedFiles object.',
            'file' => '.iak/runs/run_command_payload/changed-files.json',
            'line' => null,
            'details' => [
                'field' => 'changedFiles',
            ],
        ]],
        'nextActions' => [],
        'meta' => [
            'createdAt' => '2026-05-22T15:00:00+00:00',
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => '0.1.0',
        ],
    ]);
});

test('serializes a validate payload without version and with path/valid flags', function (): void {
    $payload = new HandoffCommandPayloadData(
        schema: 'iak.handoff.v1',
        command: 'iak:handoff',
        action: 'validate',
        status: 'invalid',
        summary: 'Handoff validation failed.',
        version: null,
        runId: 'run_validate_payload',
        path: '.iak/runs/run_validate_payload/handoff.json',
        valid: false,
        errors: [
            [
                'code' => 'handoff.path.traversal',
                'message' => 'Changed file path is outside project root.',
                'file' => '.iak/runs/run_validate_payload/handoff.json',
                'line' => null,
                'details' => ['path' => '../outside.php'],
            ],
        ],
        nextActions: [],
        meta: [
            'createdAt' => '2026-05-22T15:00:00+00:00',
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => '0.1.0',
            'source' => 'validator',
        ],
    );

    expect($payload->jsonSerialize())->toMatchArray([
        'schema' => 'iak.handoff.v1',
        'command' => 'iak:handoff',
        'action' => 'validate',
        'status' => 'invalid',
        'summary' => 'Handoff validation failed.',
        'runId' => 'run_validate_payload',
        'path' => '.iak/runs/run_validate_payload/handoff.json',
        'valid' => false,
        'errors' => [[
            'code' => 'handoff.path.traversal',
            'message' => 'Changed file path is outside project root.',
            'file' => '.iak/runs/run_validate_payload/handoff.json',
            'line' => null,
            'details' => ['path' => '../outside.php'],
        ]],
        'nextActions' => [],
        'meta' => [
            'createdAt' => '2026-05-22T15:00:00+00:00',
            'package' => 'fbarrento/inertia-agent-kit',
            'iakVersion' => '0.1.0',
            'source' => 'validator',
        ],
    ]);
});

test('serializes to array data with omitted nullable fields', function (): void {
    $payload = new HandoffCommandPayloadData(
        schema: 'iak.handoff.v1',
        command: 'iak:handoff',
        action: 'validate',
        status: 'invalid',
        summary: 'Validation failed.',
        version: null,
        runId: null,
        path: null,
        valid: false,
        errors: [],
        nextActions: [],
        meta: null,
    );

    expect($payload->toArray())->toMatchArray([
        'schema' => 'iak.handoff.v1',
        'command' => 'iak:handoff',
        'action' => 'validate',
        'status' => 'invalid',
        'summary' => 'Validation failed.',
        'valid' => false,
        'errors' => [],
        'nextActions' => [],
    ])->and(array_key_exists('version', $payload->toArray()))->toBeFalse()
        ->and(array_key_exists('runId', $payload->toArray()))->toBeFalse()
        ->and(array_key_exists('path', $payload->toArray()))->toBeFalse()
        ->and(array_key_exists('meta', $payload->toArray()))->toBeFalse();
});

test('serializes with path and non-empty array fields', function (): void {
    $payload = new HandoffCommandPayloadData(
        schema: 'iak.handoff.v1',
        command: 'iak:handoff',
        action: 'create',
        status: 'blocked',
        summary: 'Blocked create.',
        version: 1,
        changedFiles: ['page' => [['path' => 'resources/js/pages/index.tsx', 'action' => 'create']]],
        evidence: ['audit' => ['status' => 'passed']],
        artifacts: ['handoff' => ['status' => 'not_written']],
        notes: ['note'],
        nextActions: [['code' => 'retry']],
        errors: [['code' => 'x']],
        meta: ['source' => 'builder'],
        path: '.iak/runs/run_payload/path.json',
        runId: 'run_payload',
        valid: null,
    );

    expect($payload->jsonSerialize())->toMatchArray([
        'schema' => 'iak.handoff.v1',
        'command' => 'iak:handoff',
        'action' => 'create',
        'status' => 'blocked',
        'summary' => 'Blocked create.',
        'runId' => 'run_payload',
        'version' => 1,
        'path' => '.iak/runs/run_payload/path.json',
        'changedFiles' => ['page' => [['path' => 'resources/js/pages/index.tsx', 'action' => 'create']]],
        'evidence' => ['audit' => ['status' => 'passed']],
        'artifacts' => ['handoff' => ['status' => 'not_written']],
        'notes' => ['note'],
        'errors' => [['code' => 'x']],
        'nextActions' => [['code' => 'retry']],
        'meta' => ['source' => 'builder'],
    ]);
});
