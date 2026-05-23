<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use InertiaAgentKit\Feedback\FeedbackException;
use InertiaAgentKit\Feedback\FeedbackStore;
use InertiaAgentKit\Support\ProjectPaths;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-feedback-store-'.bin2hex(random_bytes(6));
    mkdir($basePath, 0755, true);

    $projectPaths = new ProjectPaths(new Application($basePath));
    $this->basePath = $basePath;
    $this->projectPaths = $projectPaths;
    $this->store = new FeedbackStore($projectPaths);

    $this->writeRaw = static function (string $path, string $contents) use ($projectPaths): void {
        $absolute = $projectPaths->basePath($path);
        $directory = dirname($absolute);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolute, $contents);
    };

    $this->removeDirectory = static function (string $path): void {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());

                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    };
});

afterEach(function (): void {
    ($this->removeDirectory)($this->basePath);
});

test('returns no records when feedback storage is missing', function (): void {
    expect($this->store->all())->toBe([]);
});

test('resolves project-relative paths to absolute filesystem paths', function (): void {
    expect($this->store->absolutePath('.iak/feedback/fbk_resolve/record.json'))
        ->toBe($this->projectPaths->basePath('.iak/feedback/fbk_resolve/record.json'));
});

test('writes, finds, and reads stored feedback records', function (): void {
    $this->store->writeRecord('fbk_find', ['id' => 'fbk_find', 'status' => 'pending']);
    $this->store->writeRecord('fbk_find_other', ['id' => 'fbk_find_other', 'status' => 'resolved']);

    $record = $this->store->find('fbk_find');
    $records = $this->store->all();

    expect($record)->toEqual(['id' => 'fbk_find', 'status' => 'pending'])
        ->and($records)->toHaveCount(2)
        ->and($records[0]['id'] ?? null)->toBeIn(['fbk_find', 'fbk_find_other']);
});

test('returns null for missing feedback records', function (): void {
    expect($this->store->find('fbk_missing'))->toBeNull();
});

test('exposes record and resolution paths for valid ids', function (): void {
    expect($this->store->recordPath('fbk_paths'))->toBe('.iak/feedback/fbk_paths/record.json')
        ->and($this->store->resolutionEvidencePath('fbk_paths'))->toBe('.iak/feedback/fbk_paths/resolution/evidence.json');
});

test('rejects invalid record ids', function (): void {
    $exceptionRecord = null;
    $exceptionResolution = null;
    $exceptionWrite = null;

    try {
        $this->store->recordPath('../invalid');
    } catch (FeedbackException $thrown) {
        $exceptionRecord = $thrown;
    }

    try {
        $this->store->resolutionEvidencePath('../invalid');
    } catch (FeedbackException $thrown) {
        $exceptionResolution = $thrown;
    }

    try {
        $this->store->writeRecord('../invalid', ['id' => '../invalid']);
    } catch (FeedbackException $thrown) {
        $exceptionWrite = $thrown;
    }

    expect($exceptionRecord)->toBeInstanceOf(FeedbackException::class)
        ->and($exceptionRecord->errorCode())->toBe('feedback.invalid_id')
        ->and($exceptionResolution)->toBeInstanceOf(FeedbackException::class)
        ->and($exceptionResolution->errorCode())->toBe('feedback.invalid_id')
        ->and($exceptionWrite)->toBeInstanceOf(FeedbackException::class)
        ->and($exceptionWrite->errorCode())->toBe('feedback.invalid_id');
});

test('rejects missing or malformed record payloads', function (): void {
    $invalidJson = null;
    $invalidType = null;
    $unreadable = null;

    ($this->writeRaw)('.iak/feedback/fbk_invalid_json/record.json', '{not-json');
    ($this->writeRaw)('.iak/feedback/fbk_invalid_type/record.json', '["value"]');
    ($this->writeRaw)('.iak/feedback/fbk_unreadable/record.json', '{"schema":"iak.feedback.v1"}');
    chmod($this->projectPaths->basePath('.iak/feedback/fbk_unreadable/record.json'), 0000);

    set_error_handler(static fn (): bool => true);

    try {
        $this->store->find('fbk_invalid_json');
    } catch (FeedbackException $thrown) {
        $invalidJson = $thrown;
    }

    try {
        $this->store->find('fbk_invalid_type');
    } catch (FeedbackException $thrown) {
        $invalidType = $thrown;
    }

    try {
        $this->store->find('fbk_unreadable');
    } catch (FeedbackException $thrown) {
        $unreadable = $thrown;
    } finally {
        restore_error_handler();
        chmod($this->projectPaths->basePath('.iak/feedback/fbk_unreadable/record.json'), 0644);
    }

    expect($invalidJson)->toBeInstanceOf(FeedbackException::class)
        ->and($invalidJson->errorCode())->toBe('feedback.record_invalid_json')
        ->and($invalidType)->toBeInstanceOf(FeedbackException::class)
        ->and($invalidType->errorCode())->toBe('feedback.record_invalid_json')
        ->and($unreadable)->toBeInstanceOf(FeedbackException::class)
        ->and($unreadable->errorCode())->toBe('feedback.record_unreadable');
});

test('throws when directories cannot be created while writing records', function (): void {
    $blockedBase = $this->basePath.'/blocked-base';
    file_put_contents($blockedBase, 'blocked');

    $blockedStore = new FeedbackStore(new ProjectPaths(new Application($blockedBase)));
    $exception = null;

    set_error_handler(static fn (): bool => true);

    try {
        $blockedStore->writeRecord('fbk_blocked_dir', ['status' => 'pending']);
    } catch (FeedbackException $thrown) {
        $exception = $thrown;
    } finally {
        restore_error_handler();
    }

    expect($exception)->toBeInstanceOf(FeedbackException::class)
        ->and($exception->errorCode())->toBe('feedback.filesystem');
});

test('throws when unable to write temporary feedback records', function (): void {
    $this->store->writeRecord('fbk_write_fail', ['status' => 'pending']);
    $writePath = $this->projectPaths->basePath('.iak/feedback/fbk_write_fail');
    chmod($writePath, 0555);

    $exception = null;

    set_error_handler(static fn (): bool => true);

    try {
        $this->store->writeRecord('fbk_write_fail', ['status' => 'pending']);
    } catch (FeedbackException $thrown) {
        $exception = $thrown;
    } finally {
        restore_error_handler();
        chmod($writePath, 0755);
    }

    expect($exception)->toBeInstanceOf(FeedbackException::class)
        ->and($exception->errorCode())->toBe('feedback.filesystem');
});

test('throws when atomic rename is not possible', function (): void {
    $absoluteDirectory = $this->projectPaths->basePath('.iak/feedback/fbk_rename_conflict');
    $absoluteConflict = $absoluteDirectory.'/record.json';
    mkdir($absoluteDirectory, 0755, true);
    mkdir($absoluteConflict, 0755, true);

    $exception = null;
    set_error_handler(static fn (): bool => true);

    try {
        $this->store->writeRecord('fbk_rename_conflict', ['status' => 'pending']);
    } catch (FeedbackException $thrown) {
        $exception = $thrown;
    } finally {
        restore_error_handler();
    }

    expect($exception)->toBeInstanceOf(FeedbackException::class)
        ->and($exception->errorCode())->toBe('feedback.filesystem');
});

test('throws runtime exception when evidence cannot be encoded as JSON', function (): void {
    expect(fn () => $this->store->writeRecord('fbk_encode_fail', ['value' => NAN]))->toThrow(RuntimeException::class);
});
