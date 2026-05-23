<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use InertiaAgentKit\Actions\BuildHandoffCommandPayloadData;
use InertiaAgentKit\Actions\BuildHandoffPayload;
use InertiaAgentKit\Actions\CreateHandoff;
use InertiaAgentKit\Actions\HandleHandoffCommand;
use InertiaAgentKit\Actions\NormalizeHandoffText;
use InertiaAgentKit\Actions\NormalizeHandoffTextList;
use InertiaAgentKit\Actions\ReadJsonArtifact;
use InertiaAgentKit\Actions\ResolveHandoffPath;
use InertiaAgentKit\Actions\ValidateHandoff;
use InertiaAgentKit\Actions\ValidateHandoffPayload;
use InertiaAgentKit\Actions\WriteJsonArtifact;
use InertiaAgentKit\Data\HandoffCommandInputData;
use InertiaAgentKit\Support\ProjectPaths;
use Tests\TestCase;
use Tests\Utils\HandoffPayloadFixture;

uses(TestCase::class);

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-handoff-command-action-'.bin2hex(random_bytes(6));
    mkdir($basePath, 0755, true);
    $this->basePath = $basePath;

    $projectPaths = new ProjectPaths(new Application($basePath));
    $this->projectPaths = $projectPaths;

    $this->handleHandoffCommand = new HandleHandoffCommand(
        new CreateHandoff(
            new ReadJsonArtifact($projectPaths, new ResolveHandoffPath),
            new WriteJsonArtifact($projectPaths, new ResolveHandoffPath),
            new ResolveHandoffPath,
            new NormalizeHandoffText,
            new NormalizeHandoffTextList,
            new BuildHandoffCommandPayloadData,
            new BuildHandoffPayload,
        ),
        new ValidateHandoff(
            new ReadJsonArtifact($projectPaths, new ResolveHandoffPath),
            new ValidateHandoffPayload,
            $projectPaths,
            new ResolveHandoffPath,
            new NormalizeHandoffText,
            new BuildHandoffCommandPayloadData,
        ),
    );
});

afterEach(function (): void {
    $basePath = $this->basePath;
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-handoff-command-action-';

    if (str_starts_with($basePath, $prefix)) {
        HandoffPayloadFixture::removeDirectory($basePath);
    }
});

test('orchestrates handoff create through one command action', function (): void {
    $result = $this->handleHandoffCommand->handle(new HandoffCommandInputData(
        action: 'create',
        payload: [
            'command' => 'iak:handoff',
            'runId' => 'run_lane_create',
            'task' => 'Create vehicle index page',
            'summary' => 'Vehicle index handoff created from orchestrator.',
            'status' => 'completed',
            'changedFile' => [
                'page:create:resources/js/pages/vehicles/index.tsx',
            ],
            'audit' => '.iak/runs/run_lane_create/audit.json',
            'tests' => '.iak/runs/run_lane_create/tests.json',
            'feedbackUnresolved' => '0',
        ],
    ), [
        'json_schemas' => [
            'handoff' => 'iak.handoff.v1',
        ],
    ]);

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(0)
        ->and($payload['status'])->toBe('completed')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written')
        ->and($payload['runId'])->toBe('run_lane_create');
});

test('orchestrates handoff validate through one command action', function (): void {
    $payload = HandoffPayloadFixture::validPayload($this->basePath);
    $payload['runId'] = 'run_lane_validate';
    $payload['artifacts']['handoff']['path'] = '.iak/runs/run_lane_validate/handoff.json';

    $handoffPath = '.iak/runs/run_lane_validate/handoff.json';
    $absolutePath = $this->projectPaths->absolute($handoffPath);
    $directory = dirname((string) $absolutePath);

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents(
        $absolutePath,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
    );

    $result = $this->handleHandoffCommand->handle(new HandoffCommandInputData(
        action: 'validate',
        path: $handoffPath,
    ));

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(0)
        ->and($payload['status'])->toBe('valid')
        ->and($payload['valid'])->toBeTrue();
});

test('returns an invalid command payload for unsupported actions', function (): void {
    $result = $this->handleHandoffCommand->handle(new HandoffCommandInputData(action: 'noop'));

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('handoff.action.invalid');
});
