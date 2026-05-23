<?php

declare(strict_types=1);

use InertiaAgentKit\Scaffolding\ResourceStubRenderer;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/../../Utils/ScaffoldingTestFunctionHooks.php';

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir().'/iak-stub-renderer-unit-'.bin2hex(random_bytes(6));
    mkdir($this->basePath.'/react', 0755, true);
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

test('renders stub templates and appends a trailing newline', function (): void {
    $stubPath = $this->basePath.'/react';
    file_put_contents($stubPath.'/hello.stub', 'Hello {{ name }}!');

    $renderer = new ResourceStubRenderer($this->basePath);

    $rendered = $renderer->render('react', 'hello.stub', ['name' => 'world']);

    expect($rendered)->toBe("Hello world!\n");
});

test('throws when the requested stub does not exist', function (): void {
    $renderer = new ResourceStubRenderer($this->basePath);

    expect(fn () => $renderer->render('react', 'missing.stub', []))
        ->toThrow(RuntimeException::class, 'Missing resource scaffold stub');
});

test('throws when a stub file cannot be read', function (): void {
    $previous = getenv('I_AK_FORCE_STUB_RENDERER_READ_FAIL');
    putenv('I_AK_FORCE_STUB_RENDERER_READ_FAIL=1');

    $stub = $this->basePath.'/react/blocked.stub';
    file_put_contents($stub, 'blocked');

    $renderer = new ResourceStubRenderer($this->basePath);

    $exception = null;

    try {
        $renderer->render('react', 'blocked.stub', []);
    } catch (RuntimeException $thrown) {
        $exception = $thrown;
    } finally {
        if ($previous === false) {
            putenv('I_AK_FORCE_STUB_RENDERER_READ_FAIL');
        } else {
            putenv('I_AK_FORCE_STUB_RENDERER_READ_FAIL='.$previous);
        }
    }

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toContain('Unable to read resource scaffold stub');
});
