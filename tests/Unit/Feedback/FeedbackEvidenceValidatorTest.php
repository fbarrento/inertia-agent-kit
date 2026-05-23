<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use InertiaAgentKit\Feedback\FeedbackEvidenceValidator;
use InertiaAgentKit\Feedback\FeedbackException;
use InertiaAgentKit\Support\ProjectPaths;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $basePath = sys_get_temp_dir().'/iak-feedback-evidence-validator-'.bin2hex(random_bytes(6));
    mkdir($basePath, 0755, true);

    $projectPaths = new ProjectPaths(new Application($basePath));
    $this->basePath = $basePath;
    $this->projectPaths = $projectPaths;
    $this->validator = new FeedbackEvidenceValidator($projectPaths);

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

test('validates evidence from allowed paths and schema files', function (): void {
    ($this->writeRaw)('.iak/runs/run_01/verify.json', json_encode([
        'schema' => 'iak.verify.v1',
        'summary' => 'Resolved via verification artifact.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

    $result = $this->validator->validate('.iak\\runs\\run_01\\verify.json', 'fbk_feedback');

    expect($result['path'])->toBe('.iak/runs/run_01/verify.json')
        ->and($result['evidence']['schema'])->toBe('iak.verify.v1')
        ->and($result['evidence']['summary'])->toBe('Resolved via verification artifact.');
});

test('normalizes feedback-resolution artifact paths', function (): void {
    ($this->writeRaw)('.iak/feedback/fbk_feedback/resolution/abc.json', json_encode([
        'schema' => 'iak.feedback.resolution.v1',
        'summary' => 'Legacy resolution artifact.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

    $result = $this->validator->validate('./.iak/feedback/fbk_feedback/resolution/./abc.json', 'fbk_feedback');

    expect($result['path'])->toBe('.iak/feedback/fbk_feedback/resolution/abc.json')
        ->and($result['evidence']['schema'])->toBe('iak.feedback.resolution.v1');
});

test('requires a project-relative evidence path', function (): void {
    $exception = null;

    try {
        $this->validator->validate('   ', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $exception = $thrown;
    }

    expect($exception)->toBeInstanceOf(FeedbackException::class)
        ->and($exception->errorCode())->toBe('feedback.evidence_required');
});

test('rejects absolute evidence paths and path traversal', function (): void {
    $exceptionAbsolute = null;
    $exceptionTraversal = null;

    try {
        $this->validator->validate('/tmp/outside.json', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $exceptionAbsolute = $thrown;
    }

    try {
        $this->validator->validate('../outside/evidence.json', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $exceptionTraversal = $thrown;
    }

    expect($exceptionAbsolute)->toBeInstanceOf(FeedbackException::class)
        ->and($exceptionAbsolute->errorCode())->toBe('feedback.evidence_invalid_path')
        ->and($exceptionTraversal)->toBeInstanceOf(FeedbackException::class)
        ->and($exceptionTraversal->errorCode())->toBe('feedback.evidence_invalid_path');
});

test('rejects invalid evidence files', function (): void {
    ($this->writeRaw)('.iak/runs/missing/invalid.json', '{not-json');
    ($this->writeRaw)('.iak/runs/missing/list.json', '["item"]');
    ($this->writeRaw)('.iak/runs/missing/unknown.json', json_encode([
        'schema' => 'iac.unknown',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    $notFound = null;
    $invalidJson = null;
    $invalidFormat = null;
    $invalidSchema = null;

    try {
        $this->validator->validate('.iak/runs/missing/not_there.json', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $notFound = $thrown;
    }

    try {
        $this->validator->validate('.iak/runs/missing/invalid.json', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $invalidJson = $thrown;
    }

    try {
        $this->validator->validate('.iak/runs/missing/list.json', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $invalidFormat = $thrown;
    }

    try {
        $this->validator->validate('.iak/runs/missing/unknown.json', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $invalidSchema = $thrown;
    }

    expect($notFound)->toBeInstanceOf(FeedbackException::class)
        ->and($notFound->errorCode())->toBe('feedback.evidence_not_found')
        ->and($invalidJson)->toBeInstanceOf(FeedbackException::class)
        ->and($invalidJson->errorCode())->toBe('feedback.evidence_invalid_json')
        ->and($invalidFormat)->toBeInstanceOf(FeedbackException::class)
        ->and($invalidFormat->errorCode())->toBe('feedback.evidence_invalid_json')
        ->and($invalidSchema)->toBeInstanceOf(FeedbackException::class)
        ->and($invalidSchema->errorCode())->toBe('feedback.evidence_invalid_schema')
        ->and($invalidSchema->details()['schema'])->toBe('iac.unknown');
});

test('rejects unreadable evidence files', function (): void {
    ($this->writeRaw)('.iak/runs/unreadable.json', '{"schema":"iak.verify.v1"}');
    $absolute = $this->projectPaths->basePath('.iak/runs/unreadable.json');
    $exception = null;

    chmod($absolute, 0000);
    set_error_handler(static fn (): bool => true);

    try {
        $this->validator->validate('.iak/runs/unreadable.json', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $exception = $thrown;
    } finally {
        restore_error_handler();
        chmod($absolute, 0644);
    }

    expect($exception)->toBeInstanceOf(FeedbackException::class)
        ->and($exception->errorCode())->toBe('feedback.evidence_unreadable');
});

test('requires evidence files to be under allowed feedback evidence directories', function (): void {
    ($this->writeRaw)('.iak/outside/unsupported.json', '{"schema":"iak.verify.v1"}');
    $exception = null;

    try {
        $this->validator->validate('.iak/outside/unsupported.json', 'fbk_feedback');
    } catch (FeedbackException $thrown) {
        $exception = $thrown;
    }

    expect($exception)->toBeInstanceOf(FeedbackException::class)
        ->and($exception->errorCode())->toBe('feedback.evidence_invalid_path')
        ->and($exception->details()['allowedPrefixes'][0])->toBe('.iak/runs/')
        ->and($exception->details()['allowedPrefixes'][1])->toBe('.iak/feedback/fbk_feedback/resolution/');
});
