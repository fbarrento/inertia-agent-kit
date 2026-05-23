<?php

declare(strict_types=1);

use InertiaAgentKit\Support\Files;

beforeEach(function (): void {
    $this->workspace = sys_get_temp_dir().'/iak-files-'.bin2hex(random_bytes(4));
    mkdir($this->workspace, 0755, true);
});

afterEach(function (): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->workspace, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $entry) {
        $path = $entry->getPathname();

        if (is_file($path)) {
            chmod($path, 0777);
        }

        if ($entry->isDir()) {
            rmdir($path);

            continue;
        }

        unlink($path);
    }

    if (is_dir($this->workspace)) {
        chmod($this->workspace, 0777);
    }

    rmdir($this->workspace);
});

test('creates directories on demand and resolves to absolute', function (): void {
    $files = new Files;
    $directory = $this->workspace.'/a/b/c';

    expect($files->ensureDirectory($directory))->toBe($directory)
        ->and(is_dir($directory))->toBeTrue();
});

test('returns existing directories without failing', function (): void {
    $directory = $this->workspace.'/existing';
    mkdir($directory, 0755, true);

    $files = new Files;

    expect($files->ensureDirectory($directory))->toBe($directory)
        ->and(is_dir($directory))->toBeTrue();
});

test('ensures parent directory is present', function (): void {
    $files = new Files;
    $file = $this->workspace.'/parent/child.txt';

    expect($files->ensureParentDirectory($file))->toBe($this->workspace.'/parent')
        ->and(is_dir($this->workspace.'/parent'))->toBeTrue();
});

test('writes and overwrites file contents', function (): void {
    $files = new Files;
    $path = $this->workspace.'/payload.txt';

    expect($files->write($path, 'first'))->toBe($path)
        ->and(file_get_contents($path))->toBe('first')
        ->and($files->write($path, 'second'))->toBe($path)
        ->and(file_get_contents($path))->toBe('second');
});

test('prevents overwriting when overwrite is disabled', function (): void {
    $files = new Files;
    $path = $this->workspace.'/locked.txt';

    $files->write($path, 'locked');

    expect(fn () => $files->write($path, 'replacement', false))->toThrow(RuntimeException::class);
});

test('throws when directory cannot be created', function (): void {
    $files = new Files;
    $blockedDir = $this->workspace.'/blocked-parent';
    file_put_contents($blockedDir, 'file');

    expect(fn () => $files->ensureDirectory($blockedDir.'/child'))->toThrow(RuntimeException::class);
});

test('throws when file cannot be written', function (): void {
    $files = new Files;
    $path = $this->workspace.'/'.str_repeat('a', 300);

    expect(fn () => $files->write($path, 'payload'))->toThrow(RuntimeException::class);
});

test('serializes and reads JSON using canonical object maps', function (): void {
    $files = new Files;
    $path = $this->workspace.'/artifact.json';

    $files->writeJson($path, ['name' => 'iac', 'items' => ['one' => 1]]);

    expect($files->readJson($path))->toBe([
        'name' => 'iac',
        'items' => ['one' => 1],
    ]);
});

test('returns null when JSON file is missing', function (): void {
    $files = new Files;

    expect($files->readJson($this->workspace.'/missing.json'))->toBeNull();
});

test('throws when JSON file is a non-empty list', function (): void {
    $files = new Files;
    $path = $this->workspace.'/list.json';

    file_put_contents($path, "[\n  \"a\",\n  \"b\"\n]\n");

    expect(fn () => $files->readJson($path))->toThrow(RuntimeException::class);
});

test('throws when JSON file cannot be read', function (): void {
    $files = new Files;
    $path = $this->workspace.'/unreadable.json';

    file_put_contents($path, '{}');
    chmod($path, 0);

    expect(fn () => $files->readJson($path))->toThrow(RuntimeException::class);
});
