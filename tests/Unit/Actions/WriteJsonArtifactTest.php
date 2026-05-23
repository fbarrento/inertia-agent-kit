<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\ResolveHandoffPath;
use InertiaAgentKit\Actions\WriteJsonArtifact;
use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Support\ProjectPaths;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-write-json-artifact-'.str_replace('.', '', uniqid('', true));

    mkdir($basePath, 0755, true);

    $this->app->setBasePath($basePath);
    $this->writeJsonArtifact = new WriteJsonArtifact(
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

    $this->readArtifact = static function (string $path): string {
        $contents = file_get_contents(base_path($path));

        if ($contents === false) {
            throw new RuntimeException("Unable to read artifact [{$path}].");
        }

        return $contents;
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
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    };
});

afterEach(function (): void {
    $basePath = base_path();
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'iak-write-json-artifact-';

    if (str_starts_with($basePath, $prefix)) {
        ($this->removeDirectory)($basePath);
    }
});

test('writes pretty JSON artifacts with unescaped slashes and unicode',
    /**
     * @throws JsonException
     */
    function (): void {
        $payload = [
            'schema' => 'iak.handoff.v1',
            'summary' => 'Ação completed for resources/js/app.tsx',
            'path' => 'resources/js/app.tsx',
        ];

        $result = $this->writeJsonArtifact->handle('.iak/runs/run_write/handoff.json', $payload, 'handoff');

        expect($result['path'])->toBe('.iak/runs/run_write/handoff.json')
            ->and($result['error'])->toBeNull()
            ->and(($this->readArtifact)('.iak/runs/run_write/handoff.json'))->toBe(
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
            );
    });

test('writes JsonSerializable object payloads',
    /**
     * @throws JsonException
     */
    function (): void {
        $payload = new class implements JsonSerializable
        {
            /**
             * @return array{schema: string, path: string}
             */
            public function jsonSerialize(): array
            {
                return [
                    'schema' => 'iak.handoff.v1',
                    'path' => 'resources/js/app.tsx',
                ];
            }
        };

        $result = $this->writeJsonArtifact->handle('.iak/runs/run_serializable/handoff.json', $payload, 'handoff');

        expect($result['path'])->toBe('.iak/runs/run_serializable/handoff.json')
            ->and($result['error'])->toBeNull()
            ->and(json_decode((string) ($this->readArtifact)('.iak/runs/run_serializable/handoff.json'), true, 512, JSON_THROW_ON_ERROR))->toBe([
                'schema' => 'iak.handoff.v1',
                'path' => 'resources/js/app.tsx',
            ]);
    });

test('returns structured write errors for invalid paths and non object payloads', function (): void {
    $invalidPath = $this->writeJsonArtifact->handle('../handoff.json', ['schema' => 'iak.handoff.v1'], '');
    $listPayload = $this->writeJsonArtifact->handle('.iak/runs/run_list/handoff.json', ['not', 'an', 'object'], 'handoff');
    $nonArrayPayload = new class implements JsonSerializable
    {
        public function jsonSerialize(): string
        {
            return 'not-an-object';
        }
    };
    $invalidSerializable = $this->writeJsonArtifact->handle('.iak/runs/run_serialized_string/handoff.json', $nonArrayPayload, 'handoff');

    expect($invalidPath['path'])->toBeNull()
        ->and($invalidPath['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($invalidPath['error']?->code)->toBe('handoff.artifact.path_invalid')
        ->and($listPayload['path'])->toBe('.iak/runs/run_list/handoff.json')
        ->and($listPayload['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($listPayload['error']?->jsonSerialize())->toBe([
            'code' => 'handoff.handoff.invalid_payload',
            'message' => 'JSON artifact [.iak/runs/run_list/handoff.json] must contain a JSON object.',
            'file' => '.iak/runs/run_list/handoff.json',
            'line' => null,
            'details' => [],
        ])
        ->and($invalidSerializable['path'])->toBe('.iak/runs/run_serialized_string/handoff.json')
        ->and($invalidSerializable['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($invalidSerializable['error']?->code)->toBe('handoff.handoff.invalid_payload');
});

test('returns structured write errors for JSON encoding and overwrite failures', function (): void {
    ($this->writeArtifact)('.iak/runs/run_existing/handoff.json', '{"schema":"iak.handoff.v1"}');

    $jsonFailure = $this->writeJsonArtifact->handle('.iak/runs/run_nan/handoff.json', ['value' => NAN], 'handoff');
    $overwriteFailure = $this->writeJsonArtifact->handle(
        '.iak/runs/run_existing/handoff.json',
        ['schema' => 'iak.handoff.v1'],
        'handoff',
        overwrite: false,
    );

    expect($jsonFailure['path'])->toBe('.iak/runs/run_nan/handoff.json')
        ->and($jsonFailure['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($jsonFailure['error']?->code)->toBe('handoff.handoff.json_encode_failed')
        ->and($jsonFailure['error']?->details)->toHaveKey('jsonError')
        ->and($overwriteFailure['path'])->toBe('.iak/runs/run_existing/handoff.json')
        ->and($overwriteFailure['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($overwriteFailure['error']?->code)->toBe('handoff.handoff.write_failed')
        ->and($overwriteFailure['error']?->details)->toMatchArray([
            'exception' => RuntimeException::class,
            'message' => 'File already exists [.iak/runs/run_existing/handoff.json].',
        ]);
});

test('returns structured write errors for directory creation and file write failures', function (): void {
    $basePath = base_path();
    $blockedBasePath = $basePath.'/blocked-base';

    file_put_contents($blockedBasePath, 'not a directory');
    set_error_handler(static fn (): bool => true);

    try {
        $this->app->setBasePath($blockedBasePath);

        $directoryFailure = (new WriteJsonArtifact(
            new ProjectPaths($this->app),
            new ResolveHandoffPath,
        ))->handle('.iak/runs/run_directory_failure/handoff.json', ['schema' => 'iak.handoff.v1'], 'handoff');
    } finally {
        $this->app->setBasePath($basePath);
        restore_error_handler();
    }

    mkdir(base_path('.iak/runs/run_write_failure/handoff.json'), 0755, true);
    set_error_handler(static fn (): bool => true);

    try {
        $writeFailure = $this->writeJsonArtifact->handle(
            '.iak/runs/run_write_failure/handoff.json',
            ['schema' => 'iak.handoff.v1'],
            'handoff',
        );
    } finally {
        restore_error_handler();
    }

    expect($directoryFailure['path'])->toBe('.iak/runs/run_directory_failure/handoff.json')
        ->and($directoryFailure['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($directoryFailure['error']?->code)->toBe('handoff.handoff.write_failed')
        ->and($directoryFailure['error']?->details)->toMatchArray([
            'exception' => RuntimeException::class,
            'message' => "Unable to create directory [{$blockedBasePath}/.iak/runs/run_directory_failure].",
        ])
        ->and($writeFailure['path'])->toBe('.iak/runs/run_write_failure/handoff.json')
        ->and($writeFailure['error'])->toBeInstanceOf(HandoffErrorData::class)
        ->and($writeFailure['error']?->code)->toBe('handoff.handoff.write_failed')
        ->and($writeFailure['error']?->details)->toMatchArray([
            'exception' => RuntimeException::class,
            'message' => 'Unable to write file [.iak/runs/run_write_failure/handoff.json].',
        ]);
});
