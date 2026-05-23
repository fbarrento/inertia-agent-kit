<?php

declare(strict_types=1);

use InertiaAgentKit\Init\Initializer;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/../../Utils/InitTestFunctionHooks.php';

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir().'/iak-init-unit-'.bin2hex(random_bytes(6));

    mkdir($this->basePath, 0755, true);
    $this->app->setBasePath($this->basePath);

    $composer = [
        'name' => 'example/app',
        'version' => '0.1.0',
        'require' => ['laravel/framework' => '12.0'],
        'require-dev' => [],
    ];

    file_put_contents($this->basePath.'/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($this->basePath.'/package.json', '{"dependencies":{"@inertiajs/react":"2.0.0"}}');
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

test('returns successful payload for supported configuration', function (): void {
    $result = (new Initializer($this->app))->run('react', false);

    expect($result['exitCode'])->toBe(0)
        ->and($result['payload']['status'])->toBe('completed')
        ->and($result['payload']['event'])->toBe('iak.init.completed.v1')
        ->and($result['payload']['errors'])->toBe([])
        ->and(file_exists(base_path('.iak/manifest/iak.manifest.v1.json')))->toBeTrue();
});

test('returns invalid payload for unsupported adapter', function (): void {
    $result = (new Initializer($this->app))->run('vue', false);

    expect($result['exitCode'])->toBe(2)
        ->and($result['payload']['status'])->toBe('failed')
        ->and($result['payload']['event'])->toBe('iak.init.failed.v1')
        ->and($result['payload']['errors'][0]['code'])->toBe('unsupported_adapter');
});

test('preserves changed generated files as skipped when force is false', function (): void {
    (new Initializer($this->app))->run('react', false);

    file_put_contents(base_path('.iak/config.json'), '{"tampered":true}');

    $result = (new Initializer($this->app))->run('react', false);

    $files = $result['payload']['files'];
    $configFile = null;

    foreach ($files as $file) {
        if (($file['path'] ?? null) === '.iak/config.json') {
            $configFile = $file;
            break;
        }
    }

    expect($configFile)->toMatchArray([
        'path' => '.iak/config.json',
        'action' => 'skipped',
        'reason' => 'existing_content_preserved',
    ]);
});

test('returns filesystem error when a tracked directory path is a file', function (): void {
    file_put_contents(base_path('.iak'), 'blocked');

    $result = (new Initializer($this->app))->run('react', false);

    expect($result['exitCode'])->toBe(2)
        ->and($result['payload']['status'])->toBe('failed')
        ->and($result['payload']['errors'][0]['code'])->toBe('init_filesystem_error');
});

test('falls back to deterministic run ids when random bytes are unavailable', function (): void {
    $previous = getenv('I_AK_FORCE_INIT_RANDOM_BYTES_THROW');
    putenv('I_AK_FORCE_INIT_RANDOM_BYTES_THROW=1');

    try {
        $result = (new Initializer($this->app))->run('react', false);

        expect($result['exitCode'])->toBe(0)
            ->and($result['payload']['status'])->toBe('completed')
            ->and($result['payload']['runId'])->toMatch('/^run_[a-f0-9]{16}$/');
    } finally {
        if ($previous === false) {
            putenv('I_AK_FORCE_INIT_RANDOM_BYTES_THROW');
        } else {
            putenv('I_AK_FORCE_INIT_RANDOM_BYTES_THROW='.$previous);
        }
    }
});

test('reports an error when writeFile target path is an existing directory', function (): void {
    mkdir(base_path('.iak'), 0755, true);

    $initializer = new Initializer($this->app);
    $writeFile = new ReflectionMethod($initializer, 'writeFile');
    $reports = [];

    expect(fn () => $writeFile->invokeArgs($initializer, ['.iak', 'cached', 'test', true, false, false, &$reports]))
        ->toThrow(RuntimeException::class, 'is not a file.');
});

test('reports an error when an existing generated file cannot be read', function (): void {
    file_put_contents(base_path('existing.txt'), 'cached');
    chmod(base_path('existing.txt'), 0000);

    $initializer = new Initializer($this->app);
    $writeFile = new ReflectionMethod($initializer, 'writeFile');
    $reports = [];

    set_error_handler(static fn (): bool => true);

    try {
        expect(fn () => $writeFile->invokeArgs($initializer, ['existing.txt', 'updated', 'test', true, true, false, &$reports]))
            ->toThrow(RuntimeException::class, 'Unable to read file [existing.txt].');
    } finally {
        restore_error_handler();
        chmod(base_path('existing.txt'), 0644);
    }
});

test('updates changed generated files when force is enabled', function (): void {
    file_put_contents(base_path('generated.txt'), 'cached');

    $initializer = new Initializer($this->app);
    $writeFile = new ReflectionMethod($initializer, 'writeFile');
    $reports = [];

    $writeFile->invokeArgs($initializer, ['generated.txt', 'updated', 'test', true, true, true, &$reports]);

    expect(file_get_contents(base_path('generated.txt')))->toBe('updated')
        ->and($reports)->toHaveCount(1)
        ->and($reports[0])->toMatchArray([
            'path' => 'generated.txt',
            'action' => 'updated',
            'kind' => 'test',
        ]);
});

test('renders rules markdown with boost-installed and boost-missing branches', function (): void {
    $initializer = new Initializer($this->app);
    $rulesMarkdown = new ReflectionMethod($initializer, 'rulesMarkdown');

    $withBoost = $rulesMarkdown->invoke($initializer, true);
    $withoutBoost = $rulesMarkdown->invoke($initializer, false);

    expect($withBoost)->toContain('Use Laravel Boost for Laravel docs')
        ->and($withoutBoost)->toContain('Laravel Boost is not detected; install it for Laravel docs');
});

test('reads malformed manifests defensively and initializes with unknown inertia version', function (): void {
    file_put_contents(base_path('package.json'), '{ malformed');

    $result = (new Initializer($this->app))->run('react', false);
    $payload = $result['payload'];

    expect($result['exitCode'])->toBe(0)
        ->and($payload['status'])->toBe('completed')
        ->and($payload['project']['inertia'])->toBe('unknown')
        ->and(file_exists(base_path('.iak/manifest/iak.manifest.v1.json')))->toBeTrue();
});
