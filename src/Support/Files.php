<?php

declare(strict_types=1);

namespace InertiaAgentKit\Support;

use JsonException;
use RuntimeException;

final class Files
{
    private readonly ProjectPaths $paths;

    public function __construct(?ProjectPaths $paths = null)
    {
        $this->paths = $paths ?? new ProjectPaths();
    }

    public function ensureDirectory(string $path): string
    {
        $absolute = $this->paths->absolute($path);

        if (! is_dir($absolute) && ! mkdir($absolute, 0755, true) && ! is_dir($absolute)) {
            throw new RuntimeException("Unable to create directory [{$path}].");
        }

        return $absolute;
    }

    public function ensureParentDirectory(string $path): string
    {
        return $this->ensureDirectory(dirname($this->paths->absolute($path)));
    }

    public function write(string $path, string $contents, bool $overwrite = true): string
    {
        $absolute = $this->paths->absolute($path);

        if (! $overwrite && is_file($absolute)) {
            throw new RuntimeException("File already exists [{$path}].");
        }

        $this->ensureParentDirectory($absolute);

        if (file_put_contents($absolute, $contents) === false) {
            throw new RuntimeException("Unable to write file [{$path}].");
        }

        return $absolute;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @throws JsonException
     */
    public function writeJson(string $path, array $value, bool $overwrite = true): string
    {
        return $this->write(
            $path,
            json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
            $overwrite
        );
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws JsonException
     */
    public function readJson(string $path): ?array
    {
        $absolute = $this->paths->absolute($path);

        if (! is_file($absolute)) {
            return null;
        }

        $contents = file_get_contents($absolute);

        if ($contents === false) {
            throw new RuntimeException("Unable to read file [{$path}].");
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException("JSON file [{$path}] must contain an object or array.");
        }

        return $decoded;
    }
}
