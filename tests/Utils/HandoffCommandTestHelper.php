<?php

declare(strict_types=1);

namespace Tests\Utils;

use FilesystemIterator;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final readonly class HandoffCommandTestHelper
{
    public function __construct(private string $basePath) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function runJson(array $arguments): array
    {
        $exitCode = Artisan::call('iak:handoff', $arguments);
        $decoded = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Expected JSON object response from handoff command.');
        }

        /** @var array<string, mixed> $decoded */

        return [$exitCode, $decoded];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function run(array $arguments): array
    {
        $exitCode = Artisan::call('iak:handoff', $arguments);

        return [$exitCode, Artisan::output()];
    }

    public function seedValidPayload(string $runId): array
    {
        $payload = HandoffPayloadFixture::validPayload($this->basePath);
        $payload['runId'] = $runId;
        $payload['artifacts']['handoff']['path'] = '.iak/runs/'.$runId.'/handoff.json';

        $payloadPath = '.iak/runs/'.$runId.'/handoff.json';

        $this->writeJson('.iak/runs/'.$runId.'/audit.json', ['schema' => 'iak.audit.v1']);
        $this->writeJson('.iak/runs/'.$runId.'/tests.json', ['schema' => 'iak.tests.v1']);
        $this->writeJson($payloadPath, $payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function writeJson(string $path, array $value): void
    {
        $this->write($path, json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ).PHP_EOL);
    }

    /**
     * @return array<string, mixed>
     */
    public function readJson(string $path): array
    {
        $absolutePath = base_path($path);
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read JSON payload for handoff command test.');
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('Expected JSON object for handoff command test fixture.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function write(string $path, string $contents): void
    {
        $absolutePath = base_path($path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, $contents);
    }

    public function removeDirectory(): void
    {
        $path = base_path();
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
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
