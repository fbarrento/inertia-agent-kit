<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use InertiaAgentKit\Actions\BuildHandoffCommandPayloadData;
use InertiaAgentKit\Actions\BuildHandoffPayload;
use InertiaAgentKit\Actions\BuildHandoffPayloadBuilder;
use InertiaAgentKit\Actions\CreateHandoff;
use InertiaAgentKit\Actions\NormalizeHandoffText;
use InertiaAgentKit\Actions\NormalizeHandoffTextList;
use InertiaAgentKit\Actions\ReadJsonArtifact;
use InertiaAgentKit\Actions\ResolveHandoffPath;
use InertiaAgentKit\Actions\WriteJsonArtifact;
use InertiaAgentKit\Data\CreateHandoffData;
use InertiaAgentKit\Support\ProjectPaths;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-create-handoff-action-'.bin2hex(random_bytes(6));
    mkdir($basePath, 0755, true);
    $this->basePath = $basePath;
    $projectPaths = new ProjectPaths(new Application($basePath));
    $this->projectPaths = $projectPaths;

    $this->writeJson = function (string $path, array $value) use ($projectPaths): void {
        $absolutePath = $projectPaths->absolute($path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $absolutePath,
            json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ).PHP_EOL,
        );
    };

    $this->createHandoff = new CreateHandoff(
        new ReadJsonArtifact($projectPaths, new ResolveHandoffPath),
        new WriteJsonArtifact($projectPaths, new ResolveHandoffPath),
        new ResolveHandoffPath,
        new NormalizeHandoffText,
        new NormalizeHandoffTextList,
        new BuildHandoffCommandPayloadData,
        new BuildHandoffPayload,
    );
});

afterEach(function (): void {
    $basePath = $this->basePath;
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-create-handoff-action-';

    if (str_starts_with($basePath, $prefix)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($basePath);
    }
});

test('creates and writes a handoff artifact from CLI-like create input', function (): void {
    $result = $this->createHandoff->handle([
        'command' => 'iak:handoff',
        'runId' => 'run_unit_create',
        'task' => 'Create vehicle index page',
        'summary' => 'Vehicle index page implemented.',
        'status' => 'completed',
        'changedFile' => [
            'page:create:resources/js/pages/vehicles/index.tsx',
            'test:create:tests/Feature/VehicleIndexTest.php',
        ],
        'audit' => '.iak/runs/run_unit_create/audit.json',
    ]);

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(0)
        ->and($payload['status'])->toBe('completed')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written')
        ->and($payload['artifacts']['handoff']['path'])->toBe('.iak/runs/run_unit_create/handoff.json');

    $absolutePath = $this->projectPaths->absolute('.iak/runs/run_unit_create/handoff.json');

    expect(file_exists($absolutePath))->toBeTrue();

    $written = json_decode((string) file_get_contents($absolutePath), true, 512, JSON_THROW_ON_ERROR);
    expect($written)->toMatchArray($payload);
});

test('merges grouped changed files from a changed-files artifact', function (): void {
    ($this->writeJson)('.iak/runs/run_unit_merge/changed-files.json', [
        'changedFiles' => [
            'page' => [[
                'path' => 'resources/js/pages/vehicles/old-index.tsx',
                'action' => 'create',
            ]],
        ],
    ]);

    $result = $this->createHandoff->handle([
        'command' => 'iak:handoff',
        'runId' => 'run_unit_merge',
        'task' => 'Update index',
        'summary' => 'Merged changed file payload.',
        'status' => 'completed',
        'changedFile' => [
            'page:modify:resources/js/pages/vehicles/index.tsx',
        ],
        'changedFilesPath' => '.iak/runs/run_unit_merge/changed-files.json',
    ]);

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(0)
        ->and($payload['changedFiles'])->toMatchArray([
            'page' => [
                ['path' => 'resources/js/pages/vehicles/old-index.tsx', 'action' => 'create'],
                ['path' => 'resources/js/pages/vehicles/index.tsx', 'action' => 'modify'],
            ],
        ]);
});

test('returns a structured payload error when changed-files artifact is not grouped', function (): void {
    ($this->writeJson)('.iak/runs/run_unit_bad_changed/changed-files.json', [
        'changedFiles' => 'bad',
    ]);

    $result = $this->createHandoff->handle([
        'command' => 'iak:handoff',
        'runId' => 'run_unit_bad_changed',
        'changedFilesPath' => '.iak/runs/run_unit_bad_changed/changed-files.json',
    ]);

    $payload = $result->payload->jsonSerialize();
    $pathExists = file_exists($this->projectPaths->absolute('.iak/runs/run_unit_bad_changed/handoff.json'));

    expect($result->status)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('handoff.changed_files.invalid_payload')
        ->and($payload['artifacts']['handoff']['status'])->toBe('not_written')
        ->and($pathExists)->toBeFalse();
});

test('returns a structured payload error when changed-files artifact cannot be read', function (): void {
    $result = $this->createHandoff->handle([
        'command' => 'iak:handoff',
        'runId' => 'run_unit_bad_path',
        'changedFilesPath' => '.iak/runs/run_unit_bad_path/missing.json',
    ]);

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(2)
        ->and($payload['errors'][0]['code'])->toBe('handoff.changed_files.missing')
        ->and($payload['artifacts']['handoff']['status'])->toBe('not_written');
});

test('returns write-failed status when handoff artifact cannot be written', function (): void {
    $runId = 'run_unit_write_block';
    $runDirectory = $this->projectPaths->absolute('.iak/runs/'.$runId);

    mkdir(dirname((string) $runDirectory), 0755, true);
    file_put_contents($runDirectory, 'blocked');

    $result = $this->createHandoff->handle([
        'command' => 'iak:handoff',
        'runId' => $runId,
        'task' => 'Cannot write handoff artifact',
        'summary' => 'Write should fail.',
        'status' => 'completed',
        'changedFile' => ['page:create:resources/js/pages/vehicles/index.tsx'],
    ]);

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(2)
        ->and($payload['status'])->toBe('blocked')
        ->and($payload['errors'][0]['code'])->toBe('handoff.handoff.write_failed')
        ->and($payload['artifacts']['handoff']['status'])->toBe('not_written');
});

test('falls back to generated path and run id when handoff payload path and runId are missing', function (): void {
    $buildPayload = new class implements BuildHandoffPayloadBuilder
    {
        public function handle(array|CreateHandoffData $createHandoffData, array $config = []): array
        {
            return [
                'schema' => 'iak.handoff.v1',
                'command' => 'iak:handoff',
                'action' => 'create',
                'status' => 'completed',
                'summary' => 'Fallback artifact path test.',
                'runId' => null,
                'version' => 1,
                'artifacts' => [
                    'handoff' => [
                        'kind' => 'json',
                        'schema' => 'iak.handoff.v1',
                    ],
                ],
                'notes' => [],
                'nextActions' => [],
                'errors' => [],
                'evidence' => [],
                'changedFiles' => (object) [],
            ];
        }
    };

    $createHandoff = new CreateHandoff(
        new ReadJsonArtifact($this->projectPaths, new ResolveHandoffPath),
        new WriteJsonArtifact($this->projectPaths, new ResolveHandoffPath),
        new ResolveHandoffPath,
        new NormalizeHandoffText,
        new NormalizeHandoffTextList,
        new BuildHandoffCommandPayloadData,
        $buildPayload,
    );

    $result = $createHandoff->handle([
        'command' => 'iak:handoff',
        'status' => 'completed',
        'summary' => 'Handoff create fallback path test.',
        'changedFile' => ['page:create:resources/js/pages/vehicles/index.tsx'],
    ]);

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(0)
        ->and($payload['artifacts']['handoff']['path'])->toBe('.iak/runs/run_not_created/handoff.json')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written');

    $absolutePath = $this->projectPaths->absolute('.iak/runs/run_not_created/handoff.json');
    expect(file_exists($absolutePath))->toBeTrue();
});

test('falls back to the resolved handoff path when a blank artifact path is returned', function (): void {
    $buildPayload = new class implements BuildHandoffPayloadBuilder
    {
        public function handle(array|CreateHandoffData $createHandoffData, array $config = []): array
        {
            return [
                'schema' => 'iak.handoff.v1',
                'command' => 'iak:handoff',
                'action' => 'create',
                'status' => 'completed',
                'summary' => 'Fallback artifact path test.',
                'runId' => 'run_unit_path_blank',
                'version' => 1,
                'artifacts' => [
                    'handoff' => [
                        'kind' => 'json',
                        'path' => ' ',
                        'schema' => 'iak.handoff.v1',
                    ],
                ],
                'notes' => [],
                'nextActions' => [],
                'errors' => [],
                'evidence' => [],
                'changedFiles' => (object) [],
            ];
        }
    };

    $createHandoff = new CreateHandoff(
        new ReadJsonArtifact($this->projectPaths, new ResolveHandoffPath),
        new WriteJsonArtifact($this->projectPaths, new ResolveHandoffPath),
        new ResolveHandoffPath,
        new NormalizeHandoffText,
        new NormalizeHandoffTextList,
        new BuildHandoffCommandPayloadData,
        $buildPayload,
    );

    $result = $createHandoff->handle([
        'command' => 'iak:handoff',
        'runId' => 'run_unit_path_blank',
        'status' => 'completed',
        'summary' => 'Handoff create fallback path test.',
        'changedFile' => ['page:create:resources/js/pages/vehicles/index.tsx'],
    ]);

    $payload = $result->payload->jsonSerialize();

    expect($result->status)->toBe(0)
        ->and($payload['artifacts']['handoff']['path'])->toBe('.iak/runs/run_unit_path_blank/handoff.json')
        ->and($payload['runId'])->toBe('run_unit_path_blank')
        ->and($payload['artifacts']['handoff']['status'])->toBe('written');

    $absolutePath = $this->projectPaths->absolute('.iak/runs/run_unit_path_blank/handoff.json');
    expect(file_exists($absolutePath))->toBeTrue();
});
