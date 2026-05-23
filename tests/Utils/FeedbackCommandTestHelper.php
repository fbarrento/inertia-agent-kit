<?php

declare(strict_types=1);

namespace Tests\Utils;

use FilesystemIterator;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class FeedbackCommandTestHelper
{
    public function __construct(private string $basePath) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function call(array $arguments): array
    {
        $exitCode = Artisan::call('iak:feedback', [
            ...$arguments,
            '--json' => true,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        return [$exitCode, $payload];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function writeRecord(array $overrides = []): array
    {
        $id = (string) ($overrides['id'] ?? 'fbk_default');
        $record = array_replace_recursive([
            'schema' => 'iak.feedback.v1',
            'id' => $id,
            'status' => 'pending',
            'surface' => 'app',
            'source' => 'human',
            'producer' => 'iak.test',
            'target' => [
                'url' => 'http://localhost/vehicles',
                'route' => 'vehicles.index',
                'storyId' => null,
                'selector' => "[data-iak-part='filter-bar']",
            ],
            'viewport' => [
                'width' => 1440,
                'height' => 900,
                'name' => 'desktop',
            ],
            'message' => 'This should reuse the standard filter bar pattern.',
            'tags' => [
                'pattern',
                'filter-bar',
            ],
            'attachments' => [
                'screenshot' => ".iak/feedback/{$id}/screenshot.png",
                'dom' => ".iak/feedback/{$id}/dom.html",
                'console' => ".iak/feedback/{$id}/console.json",
                'network' => null,
                'trace' => null,
            ],
            'context' => [
                'gitSha' => null,
                'branch' => 'feat/feedback',
                'adapter' => 'laravel-inertia-react',
                'componentCandidates' => [
                    'FilterBar',
                ],
                'storyArgs' => null,
                'testRunId' => null,
            ],
            'resolution' => null,
            'createdAt' => '2026-05-22T15:00:00Z',
            'updatedAt' => '2026-05-22T15:00:00Z',
        ], $overrides);

        $this->writeJson(".iak/feedback/{$id}/record.json", $record);

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function readRecord(string $id): array
    {
        return $this->readJson(".iak/feedback/{$id}/record.json");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function writeJson(string $relativePath, array $payload): void
    {
        $this->writeRaw(
            $relativePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    public function writeRaw(string $relativePath, string $contents): void
    {
        $path = base_path($relativePath);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);
    }

    /**
     * @return array<string, mixed>
     */
    public function readJson(string $relativePath): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents(base_path($relativePath)), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    public function removeDirectory(): void
    {
        if (! is_dir($this->basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->basePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());

                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($this->basePath);
    }
}
