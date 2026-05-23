<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\BuildHandoffCommandPayloadData;
use InertiaAgentKit\Data\HandoffCommandPayloadData;

final readonly class SerializableFixtureItem implements JsonSerializable
{
    public function __construct(
        private string $code,
    ) {}

    public function jsonSerialize(): array
    {
        return ['code' => $this->code];
    }
}

test('builds normalized payload data with notes, actions, and metadata', function (): void {
    $builder = new BuildHandoffCommandPayloadData;
    $payload = $builder->handle([
        'schema' => 'custom.schema',
        'command' => 'iak:handoff',
        'action' => 'create',
        'status' => 'completed',
        'summary' => 'Handoff created.',
        'runId' => 'run_payload_builder',
        'version' => '2',
        'notes' => ['  first note  ', '', 'second note'],
        'nextActions' => [
            ['code' => 'retry'],
            new SerializableFixtureItem('json.serializable'),
        ],
        'errors' => [
            ['code' => 'first'],
            new SerializableFixtureItem('second'),
        ],
        'path' => ' ',
    ], [
        'createdAt' => '2026-05-22T16:00:00Z',
        'package' => 'fbarrento/inertia-agent-kit',
    ], 'fallback.schema');

    expect($payload)->toBeInstanceOf(HandoffCommandPayloadData::class)
        ->and($payload->schema)->toBe('custom.schema')
        ->and($payload->version)->toBe(2)
        ->and($payload->notes)->toBe(['  first note  ', 'second note'])
        ->and($payload->nextActions)->toBe([
            ['code' => 'retry'],
            ['code' => 'json.serializable'],
        ])
        ->and($payload->errors)->toBe([
            ['code' => 'first'],
            ['code' => 'second'],
        ])
        ->and($payload->path)->toBeNull()
        ->and($payload->meta)->toMatchArray([
            'createdAt' => '2026-05-22T16:00:00Z',
            'package' => 'fbarrento/inertia-agent-kit',
        ]);
});

test('preserves non-numeric versions as raw values', function (): void {
    $builder = new BuildHandoffCommandPayloadData;
    $payload = $builder->handle([
        'schema' => 'iak.handoff.v1',
        'command' => 'iak:handoff',
        'action' => 'validate',
        'status' => 'invalid',
        'summary' => 'Non-numeric version',
        'version' => 'release',
    ], [], 'fallback');

    expect($payload->version)->toBe('release');
});

test('normalizes an empty version string to null', function (): void {
    $builder = new BuildHandoffCommandPayloadData;

    $payload = $builder->handle([
        'schema' => 'iak.handoff.v1',
        'command' => 'iak:handoff',
        'action' => 'create',
        'status' => 'completed',
        'summary' => 'Blank version test',
        'version' => '   ',
    ], [], 'fallback.schema');

    expect($payload->version)->toBeNull();
});
