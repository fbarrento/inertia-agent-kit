<?php

declare(strict_types=1);

namespace InertiaAgentKit\Feedback;

use InertiaAgentKit\Support\ArrayData;
use InertiaAgentKit\Support\ProjectPaths;
use JsonException;
use RuntimeException;

final readonly class FeedbackStore
{
    /** @var array<int, string> */
    public const STATUSES = [
        'pending',
        'in_progress',
        'resolved',
        'wont_fix',
        'duplicate',
    ];

    public function __construct(
        private ProjectPaths $paths,
        private string $feedbackPath = '.iak/feedback',
    ) {}

    public function feedbackPath(): string
    {
        return $this->feedbackPath;
    }

    public function recordPath(string $id): string
    {
        $this->assertValidId($id);

        return "{$this->feedbackPath}/{$id}/record.json";
    }

    public function resolutionEvidencePath(string $id): string
    {
        $this->assertValidId($id);

        return "{$this->feedbackPath}/{$id}/resolution/evidence.json";
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $root = $this->paths->basePath($this->feedbackPath);

        if (! is_dir($root)) {
            return [];
        }

        $files = glob($root.'/*/record.json') ?: [];
        $records = [];

        foreach ($files as $file) {
            $relative = $this->paths->relative($file);
            $records[] = $this->readRecord($relative);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $path = $this->recordPath($id);
        $absolute = $this->paths->basePath($path);

        if (! is_file($absolute)) {
            return null;
        }

        return $this->readRecord($path);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function writeRecord(string $id, array $record): string
    {
        return $this->writeJsonAtomic($this->recordPath($id), $record);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function writeResolutionEvidence(string $id, array $evidence): string
    {
        return $this->writeJsonAtomic($this->resolutionEvidencePath($id), $evidence);
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->paths->basePath($relativePath);
    }

    /**
     * @return array<string, mixed>
     */
    private function readRecord(string $relativePath): array
    {
        $absolute = $this->paths->basePath($relativePath);
        $contents = file_get_contents($absolute);

        if ($contents === false) {
            throw new FeedbackException(
                'feedback.record_unreadable',
                "Unable to read feedback record [{$relativePath}].",
                3,
                $relativePath,
            );
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new FeedbackException(
                'feedback.record_invalid_json',
                "Feedback record [{$relativePath}] is not valid JSON.",
                2,
                $relativePath,
                ['jsonError' => $exception->getMessage()],
            );
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new FeedbackException(
                'feedback.record_invalid_json',
                "Feedback record [{$relativePath}] must contain a JSON object.",
                2,
                $relativePath,
            );
        }

        return ArrayData::stringMap($decoded);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function writeJsonAtomic(string $relativePath, array $value): string
    {
        $absolute = $this->paths->basePath($relativePath);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new FeedbackException(
                'feedback.filesystem',
                "Unable to create directory [{$this->paths->relative($directory)}].",
                3,
                $this->paths->relative($directory),
            );
        }

        try {
            $contents = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode feedback JSON.', previous: $exception);
        }

        $temporary = $absolute.'.tmp';

        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new FeedbackException(
                'feedback.filesystem',
                "Unable to write feedback artifact [{$relativePath}].",
                3,
                $relativePath,
            );
        }

        if (! rename($temporary, $absolute)) {
            @unlink($temporary);

            throw new FeedbackException(
                'feedback.filesystem',
                "Unable to replace feedback artifact [{$relativePath}].",
                3,
                $relativePath,
            );
        }

        return $relativePath;
    }

    private function assertValidId(string $id): void
    {
        if ($id === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $id) !== 1) {
            throw new FeedbackException(
                'feedback.invalid_id',
                'Feedback id must be a safe path segment.',
                2,
                null,
                ['id' => $id],
            );
        }
    }
}
