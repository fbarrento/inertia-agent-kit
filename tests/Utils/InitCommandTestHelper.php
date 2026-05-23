<?php

declare(strict_types=1);

namespace Tests\Utils;

final class InitCommandTestHelper
{
    public static function actionForPath(array $payload, string $path): ?string
    {
        foreach ($payload['files'] ?? [] as $file) {
            if (($file['path'] ?? null) === $path) {
                return is_string($file['action'] ?? null) ? $file['action'] : null;
            }
        }

        return null;
    }
}
