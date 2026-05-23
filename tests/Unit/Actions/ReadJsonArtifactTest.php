<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\ReadJsonArtifact;
use InertiaAgentKit\Actions\ResolveHandoffPath;
use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Support\ProjectPaths;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-read-json-artifact-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0755, true);

    $this->app->setBasePath($basePath);
    $this->readJsonArtifact = new ReadJsonArtifact(
        new ProjectPaths($this->app),
        new ResolveHandoffPath,
    );

    $this->writeArtifact = static function (string $path, string $contents): void {
        $absolutePath = base_path($path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, $contents);
    };

    $this->writeJsonArtifact = function (string $path, array $value): void {
        ($this->writeArtifact)(
            $path,
            json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    };

    $this->removeDirectory = static function (string $path): void {
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
                chmod($item->getPathname(), 0644);
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    };
});

afterEach(function (): void {
    $basePath = base_path();
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-read-json-artifact-';

    if (str_starts_with($basePath, $prefix)) {
        ($this->removeDirectory)($basePath);
    }
});

test('reads JSON artifacts as normalized object payloads',
    /**
     * @throws JsonException
     */
    function (): void {
        ($this->writeJsonArtifact)('.iak/runs/run_read/handoff.json', [
            'schema' => 'iak.handoff.v1',
            'runId' => 'run_read',
            'nested' => [
                'path' => 'resources/js/pages/vehicles/index.tsx',
            ],
        ]);

        $result = $this->readJsonArtifact->handle('./.iak//runs/run_read/handoff.json', 'handoff');

        expect($result['path'])->toBe('.iak/runs/run_read/handoff.json')
            ->and($result['payload'])->toBe([
                'schema' => 'iak.handoff.v1',
                'runId' => 'run_read',
                'nested' => [
                    'path' => 'resources/js/pages/vehicles/index.tsx',
                ],
            ])
            ->and($result['error'])->toBeNull();
    });

test('returns structured errors for invalid paths and missing artifacts', function (): void {
    $invalidPath = $this->readJsonArtifact->handle('../handoff.json', '');
    $missing = $this->readJsonArtifact->handle('.iak/runs/missing/handoff.json', 'handoff');

    expect($invalidPath['path'])->toBeNull()
        ->and($invalidPath['payload'])->toBeNull()
        ->and($invalidPath['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($invalidPath['error']?->jsonSerialize())->toBe([
            'code' => 'handoff.artifact.path_invalid',
            'message' => 'Path must be project-relative and must not contain traversal or .git segments.',
            'file' => '../handoff.json',
            'line' => null,
            'details' => [],
        ])
        ->and($missing['path'])->toBe('.iak/runs/missing/handoff.json')
        ->and($missing['payload'])->toBeNull()
        ->and($missing['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($missing['error']?->code)->toBe('handoff.handoff.missing');
});

test('returns structured errors for unreadable invalid JSON and list payload artifacts', function (): void {
    ($this->writeArtifact)('.iak/runs/run_unreadable/handoff.json', '{"schema":"iak.handoff.v1"}');
    ($this->writeArtifact)('.iak/runs/run_invalid_json/handoff.json', '{"schema":');
    ($this->writeArtifact)('.iak/runs/run_list_payload/handoff.json', '["not", "an", "object"]');

    chmod(base_path('.iak/runs/run_unreadable/handoff.json'), 0000);
    set_error_handler(static fn (): bool => true);

    try {
        $unreadable = $this->readJsonArtifact->handle('.iak/runs/run_unreadable/handoff.json', 'handoff');
    } finally {
        restore_error_handler();
        chmod(base_path('.iak/runs/run_unreadable/handoff.json'), 0644);
    }

    $invalidJson = $this->readJsonArtifact->handle('.iak/runs/run_invalid_json/handoff.json', 'handoff');
    $listPayload = $this->readJsonArtifact->handle('.iak/runs/run_list_payload/handoff.json', 'handoff');

    expect($unreadable['path'])->toBe('.iak/runs/run_unreadable/handoff.json')
        ->and($unreadable['payload'])->toBeNull()
        ->and($unreadable['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($unreadable['error']?->code)->toBe('handoff.handoff.read_failed')
        ->and($invalidJson['path'])->toBe('.iak/runs/run_invalid_json/handoff.json')
        ->and($invalidJson['payload'])->toBeNull()
        ->and($invalidJson['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($invalidJson['error']?->code)->toBe('handoff.handoff.invalid_json')
        ->and($invalidJson['error']?->details)->toHaveKey('jsonError')
        ->and($listPayload['path'])->toBe('.iak/runs/run_list_payload/handoff.json')
        ->and($listPayload['payload'])->toBeNull()
        ->and($listPayload['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($listPayload['error']?->code)->toBe('handoff.handoff.invalid_payload');
});
