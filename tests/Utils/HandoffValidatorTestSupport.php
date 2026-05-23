<?php

declare(strict_types=1);

namespace Tests\Utils;

use InertiaAgentKit\Handoff\HandoffValidator;

final class HandoffValidatorTestSupport
{
    /**
     * @param  callable(array<string, mixed>, string): array<string, mixed>|null  $mutate
     * @return array{valid: bool, status: string, errors: list<array<string, mixed>>, nextActions: list<array<string, mixed>>}
     */
    public static function validatePayload(?callable $mutate = null): array
    {
        $basePath = HandoffPayloadFixture::makeBasePath();

        try {
            $payload = HandoffPayloadFixture::validPayload($basePath);

            if ($mutate !== null) {
                $payload = $mutate($payload, $basePath) ?? $payload;
            }

            return (new HandoffValidator)->validate($payload, $basePath);
        } finally {
            HandoffPayloadFixture::removeDirectory($basePath);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, status: string, errors: list<array<string, mixed>>, nextActions: list<array<string, mixed>>}
     */
    public static function validate(array $payload, string $basePath): array
    {
        return (new HandoffValidator)->validate($payload, $basePath);
    }

    /**
     * @param  array{errors: list<array<string, mixed>>}  $result
     * @return list<string>
     */
    public static function errorCodes(array $result): array
    {
        return array_values(array_map(
            static fn (array $error): string => (string) $error['code'],
            $result['errors'],
        ));
    }

    public static function writeFile(string $basePath, string $path, string $contents): void
    {
        $absolutePath = $basePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, $contents);
    }
}
