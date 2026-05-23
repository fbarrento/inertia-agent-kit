<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use InertiaAgentKit\Support\ProjectPaths;

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir().'/iak-project-paths-'.bin2hex(random_bytes(4));
    mkdir($this->basePath, 0755, true);

    $this->app = Mockery::mock(Application::class);
    $this->app->shouldReceive('basePath')->andReturn($this->basePath);

    $this->paths = new ProjectPaths($this->app);
});

afterEach(function (): void {
    if (is_dir($this->basePath)) {
        rmdir($this->basePath);
    }
});

test('resolves base path from an injected application and joins segments', function (): void {
    expect($this->paths->basePath())->toBe($this->basePath)
        ->and($this->paths->basePath('src'))->toBe($this->basePath.'/src')
        ->and($this->paths->join($this->basePath, 'src', 'Support'))->toBe($this->basePath.'/src/Support');
});

test('returns normalized absolute paths', function (): void {
    expect($this->paths->absolute('/tmp/iak/unit/../unit/handoff.json'))->toBe('/tmp/iak/unit/handoff.json')
        ->and($this->paths->relative($this->basePath.'/src/Support'))->toBe('src/Support')
        ->and($this->paths->relative($this->basePath))->toBe('');
});

test('normalizes dot segments and empty inputs', function (): void {
    expect($this->paths->normalize(''))->toBe('')
        ->and($this->paths->normalize('a/./b/../c'))->toBe('a/c')
        ->and($this->paths->normalize('/a/./b/../c'))->toBe('/a/c');
});

test('converts Windows separators to POSIX-style separators', function (): void {
    expect($this->paths->toUnix('C:\\tmp\\iak\\..\\handoff'))->toBe('C:/tmp/iak/../handoff');
});

test('uses relative conversion for paths outside the base path', function (): void {
    expect($this->paths->relative('/tmp/external/path'))->toBe('/tmp/external/path')
        ->and($this->paths->join('', 'a', '', 'b'))->toBe('a/b');
});

test('retains relative parent traversal at the root of a relative path', function (): void {
    expect($this->paths->normalize('../assets/../shared'))->toBe('..'.DIRECTORY_SEPARATOR.'shared');
});

test('normalizes windows drive paths with preserved drive segment', function (): void {
    expect($this->paths->normalize('C:\\unit\\agent\\..\\kit'))->toBe('C:'.DIRECTORY_SEPARATOR.'unit'.DIRECTORY_SEPARATOR.'kit');
});
