<?php

declare(strict_types=1);

use InertiaAgentKit\Console\InitCommand;
use Symfony\Component\Console\Input\InputInterface;
use Tests\Utils\InitCommandTestHelper;

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir().'/iak-init-test-'.bin2hex(random_bytes(6));

    mkdir($this->basePath, 0755, true);
    $this->app->setBasePath($this->basePath);
    $this->path = fn (string $path): string => $this->basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
});

afterEach(function (): void {
    $directory = $this->basePath ?? null;

    if (! is_string($directory) || ! is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
});

test('emits one init result json object and creates the expected files under the app base path', function (): void {
    $exitCode = Artisan::call('iak:init', [
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload)->toMatchArray([
            'schema' => 'iak.init.result.v1',
            'event' => 'iak.init.completed.v1',
            'status' => 'completed',
            'manifest' => [
                'schema' => 'iak.manifest.v1',
                'path' => '.iak/manifest/iak.manifest.v1.json',
                'status' => 'valid',
            ],
            'errors' => [],
        ]);

    foreach ([
        'config/inertia-agent-kit.php',
        'iak.config.json',
        '.iak/config.json',
        '.iak/state/init.json',
        '.iak/manifest/iak.manifest.v1.json',
        '.iak/schemas/iak.init.result.v1.schema.json',
        '.iak/schemas/iak.manifest.v1.schema.json',
        '.iak/rules/inertia-agent-kit.md',
    ] as $file) {
        expect(is_file(($this->path)($file)))->toBeTrue();
    }

    foreach ([
        '.iak/schemas',
        '.iak/feedback',
        '.iak/runs',
    ] as $directory) {
        expect(is_dir(($this->path)($directory)))->toBeTrue();
    }

    $manifest = json_decode(
        file_get_contents(($this->path)('.iak/manifest/iak.manifest.v1.json')) ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($manifest)->toHaveKeys([
        'project',
        'adapter',
        'conventions',
        'commands',
        'schemas',
        'boost',
        'artifacts',
    ]);
    expect($manifest['commands'])->toHaveKeys([
        'init',
        'makeResource',
        'audit',
        'feedbackList',
        'feedbackShow',
        'feedbackResolve',
        'verify',
    ])
        ->and($manifest['commands'])->not->toHaveKey('manifest');
});

test('reports existing generated files as unchanged on a second run', function (): void {
    Artisan::call('iak:init', [
        '--json' => true,
    ]);

    $exitCode = Artisan::call('iak:init', [
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(InitCommandTestHelper::actionForPath($payload, 'config/inertia-agent-kit.php'))->toBe('unchanged')
        ->and(InitCommandTestHelper::actionForPath($payload, 'iak.config.json'))->toBe('unchanged')
        ->and(InitCommandTestHelper::actionForPath($payload, '.iak/manifest/iak.manifest.v1.json'))->toBe('unchanged');
});

test('preserves user edited source controlled config files', function (): void {
    Artisan::call('iak:init', [
        '--json' => true,
    ]);

    $phpConfig = "<?php\n\nreturn ['adapter' => 'custom'];\n";
    $jsonConfig = json_encode([
        'schema' => 'iak.config.v1',
        'project' => [
            'adapter' => 'custom',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

    file_put_contents(($this->path)('config/inertia-agent-kit.php'), $phpConfig);
    file_put_contents(($this->path)('iak.config.json'), $jsonConfig);

    $exitCode = Artisan::call('iak:init', [
        '--json' => true,
        '--force' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(file_get_contents(($this->path)('config/inertia-agent-kit.php')))->toBe($phpConfig)
        ->and(file_get_contents(($this->path)('iak.config.json')))->toBe($jsonConfig)
        ->and(InitCommandTestHelper::actionForPath($payload, 'config/inertia-agent-kit.php'))->toBe('skipped')
        ->and(InitCommandTestHelper::actionForPath($payload, 'iak.config.json'))->toBe('skipped');
});

test('returns structured json and exit code two for unsupported adapters', function (): void {
    $exitCode = Artisan::call('iak:init', [
        '--json' => true,
        '--adapter' => 'vue',
    ]);

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(2)
        ->and($payload['schema'])->toBe('iak.init.result.v1')
        ->and($payload['event'])->toBe('iak.init.failed.v1')
        ->and($payload['status'])->toBe('failed')
        ->and($payload['errors'][0]['code'])->toBe('unsupported_adapter')
        ->and(file_exists(($this->path)('iak.config.json')))->toBeFalse()
        ->and(file_exists(($this->path)('.iak')))->toBeFalse();
});

test('pretty prints json when requested', function (): void {
    $exitCode = Artisan::call('iak:init', [
        '--json' => true,
        '--pretty' => true,
    ]);

    $output = Artisan::output();
    $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('iak.init.result.v1')
        ->and($output)->toContain(PHP_EOL.'    "event": "iak.init.completed.v1"');
});

test('returns plain text output when json flag is not set', function (): void {
    $exitCode = Artisan::call('iak:init', []);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('IAK initialized for Laravel Inertia React.');
});

test('normalizes adapter option when option value is non-scalar', function (): void {
    $command = app(InitCommand::class);
    $input = Mockery::mock(InputInterface::class);

    $input->shouldReceive('getOption')->with('adapter')->andReturn(['react']);
    $input->shouldReceive('getArgument')->with('resource')->andReturnNull();

    $inputProperty = new ReflectionProperty($command, 'input');
    $inputProperty->setValue($command, $input);

    $method = new ReflectionMethod($command, 'nullableOption');

    $value = $method->invoke($command, 'adapter');

    expect($value)->toBeNull();
});

test('normalizes adapter option when option value is boolean', function (): void {
    $command = app(InitCommand::class);
    $input = Mockery::mock(InputInterface::class);

    $input->shouldReceive('getOption')->with('adapter')->andReturn(false);

    $inputProperty = new ReflectionProperty($command, 'input');
    $inputProperty->setValue($command, $input);

    $method = new ReflectionMethod($command, 'nullableOption');

    expect($method->invoke($command, 'adapter'))->toBeNull();
});
