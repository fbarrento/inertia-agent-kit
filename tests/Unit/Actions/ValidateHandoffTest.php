<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use InertiaAgentKit\Actions\BuildHandoffCommandPayloadData;
use InertiaAgentKit\Actions\NormalizeHandoffText;
use InertiaAgentKit\Actions\ReadJsonArtifact;
use InertiaAgentKit\Actions\ResolveHandoffPath;
use InertiaAgentKit\Actions\ValidateHandoff;
use InertiaAgentKit\Actions\ValidateHandoffPayload;
use InertiaAgentKit\Support\ProjectPaths;
use Tests\TestCase;
use Tests\Utils\HandoffPayloadFixture;

uses(TestCase::class);

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-validate-handoff-action-'.bin2hex(random_bytes(6));
    mkdir($basePath, 0755, true);
    $this->basePath = $basePath;
    $projectPaths = new ProjectPaths(new Application($basePath));
    $this->projectPaths = $projectPaths;

    $this->writeJson = function (string $path, array $payload) use ($projectPaths): void {
        $absolutePath = $projectPaths->absolute($path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $absolutePath,
            json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ).PHP_EOL,
        );
    };

    $this->removeDirectory = function (string $path): void {
        if (! is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    };

    $this->validateHandoff = new ValidateHandoff(
        new ReadJsonArtifact($projectPaths, new ResolveHandoffPath),
        new ValidateHandoffPayload,
        $projectPaths,
        new ResolveHandoffPath,
        new NormalizeHandoffText,
        new BuildHandoffCommandPayloadData,
    );
});

afterEach(function (): void {
    $basePath = $this->basePath;
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-validate-handoff-action-';

    if (str_starts_with($basePath, $prefix)) {
        ($this->removeDirectory)($basePath);
    }
});

test('validates an existing handoff artifact', function (): void {
    $payload = HandoffPayloadFixture::validPayload($this->basePath);
    ($this->writeJson)('.iak/runs/run_01/handoff.json', $payload);

    $result = $this->validateHandoff->handle('.iak/runs/run_01/handoff.json', null);
    $serialized = $result->payload->jsonSerialize();

    expect($result->status)->toBe(0)
        ->and($serialized['status'])->toBe('valid')
        ->and($serialized['valid'])->toBeTrue()
        ->and($serialized['errors'])->toBe([]);
});

test('returns a validation error when no path or run id is provided', function (): void {
    $result = $this->validateHandoff->handle(null, null);

    $serialized = $result->payload->jsonSerialize();

    expect($result->status)->toBe(2)
        ->and($serialized['status'])->toBe('invalid')
        ->and($serialized['valid'])->toBeFalse()
        ->and($serialized['errors'][0]['code'])->toBe('handoff.path.required');
});

test('returns read errors for missing artifacts by explicit path', function (): void {
    $result = $this->validateHandoff->handle('.iak/runs/run_missing/handoff.json', null);

    $serialized = $result->payload->jsonSerialize();

    expect($result->status)->toBe(2)
        ->and($serialized['errors'][0]['code'])->toBe('handoff.handoff.missing')
        ->and($serialized['path'])->toBe('.iak/runs/run_missing/handoff.json');
});

test('returns read errors for missing artifacts resolved from run id', function (): void {
    $result = $this->validateHandoff->handle(null, 'run_missing_by_id');

    $serialized = $result->payload->jsonSerialize();

    expect($result->status)->toBe(2)
        ->and($serialized['errors'][0]['code'])->toBe('handoff.handoff.missing')
        ->and($serialized['path'])->toBe('.iak/runs/run_missing_by_id/handoff.json');
});

test('returns validation errors for invalid payload content', function (): void {
    $payload = HandoffPayloadFixture::validPayload($this->basePath);
    $payload['runId'] = 'run_invalid_payload';
    $payload['artifacts']['handoff']['path'] = '.iak/runs/run_invalid_payload/handoff.json';
    $payload['changedFiles']['page'][0]['path'] = '../outside.php';

    ($this->writeJson)('.iak/runs/run_invalid_payload/handoff.json', $payload);

    $result = $this->validateHandoff->handle('.iak/runs/run_invalid_payload/handoff.json', null);
    $serialized = $result->payload->jsonSerialize();

    expect($result->status)->toBe(2)
        ->and($serialized['status'])->toBe('invalid')
        ->and($serialized['valid'])->toBeFalse()
        ->and($serialized['errors'][0]['code'])->toBe('handoff.changed_files.path_invalid');
});

test('falls back to the command run id when payload runId is blank', function (): void {
    $payload = HandoffPayloadFixture::validPayload($this->basePath);
    $payload['runId'] = '   ';
    $payload['artifacts']['handoff']['path'] = '.iak/runs/run_validate_fallback/handoff.json';

    ($this->writeJson)('.iak/runs/run_validate_fallback/handoff.json', $payload);

    $result = $this->validateHandoff->handle('.iak/runs/run_validate_fallback/handoff.json', 'run_from_command');
    $serialized = $result->payload->jsonSerialize();

    expect($result->status)->toBe(0)
        ->and($serialized['runId'])->toBe('run_from_command')
        ->and($serialized['status'])->toBe('valid')
        ->and($serialized['valid'])->toBeTrue();
});
