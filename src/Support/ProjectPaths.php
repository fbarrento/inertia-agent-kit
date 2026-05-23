<?php

declare(strict_types=1);

namespace InertiaAgentKit\Support;

use Illuminate\Contracts\Foundation\Application;

final readonly class ProjectPaths
{
    public function __construct(
        private ?Application $app = null
    ) {}

    public function basePath(?string $path = null): string
    {
        $base = $this->app?->basePath() ?? (function_exists('base_path') ? base_path() : (string) getcwd());

        if ($path === null || $path === '') {
            return $this->normalize($base);
        }

        return $this->join($base, $path);
    }

    public function absolute(string $path): string
    {
        if ($this->isAbsolute($path)) {
            return $this->normalize($path);
        }

        return $this->basePath($path);
    }

    public function relative(string $path): string
    {
        $base = $this->basePath();
        $absolute = $this->absolute($path);

        if ($absolute === $base) {
            return '';
        }

        $prefix = $base.DIRECTORY_SEPARATOR;

        if (str_starts_with($absolute, $prefix)) {
            return $this->toUnix(substr($absolute, strlen($prefix)));
        }

        return $this->toUnix($path);
    }

    public function join(string ...$parts): string
    {
        return $this->normalize(implode(DIRECTORY_SEPARATOR, array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    public function normalize(string $path): string
    {
        $path = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));

        if ($path === '') {
            return '';
        }

        $drive = '';
        $absolute = $this->isAbsolute($path);

        if (preg_match('/^[A-Za-z]:'.preg_quote(DIRECTORY_SEPARATOR, '/').'/', $path) === 1) {
            $drive = substr($path, 0, 2);
            $path = substr($path, 2);
        }

        $segments = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments !== [] && end($segments) !== '..') {
                    array_pop($segments);

                    continue;
                }

                if (! $absolute) {
                    $segments[] = $segment;
                }

                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $segments);

        if ($drive !== '') {
            return $drive.DIRECTORY_SEPARATOR.$normalized;
        }

        return $absolute ? DIRECTORY_SEPARATOR.$normalized : $normalized;
    }

    public function toUnix(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
