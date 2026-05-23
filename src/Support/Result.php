<?php

declare(strict_types=1);

namespace InertiaAgentKit\Support;

final class Result
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function ok(string $schema, array $data = [], array $meta = []): array
    {
        return self::shape($schema, 'ok', $data, $meta);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function pending(string $schema, string $message, array $data = []): array
    {
        return self::shape($schema, 'pending', [
            ...$data,
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function error(string $schema, string $code, string $message, array $context = []): array
    {
        return [
            'schema' => $schema,
            'status' => 'error',
            'error' => [
                'code' => $code,
                'message' => $message,
                'context' => $context === [] ? (object) [] : $context,
            ],
            'meta' => self::meta(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function shape(string $schema, string $status, array $data, array $meta = []): array
    {
        return [
            'schema' => $schema,
            'status' => $status,
            'data' => $data === [] ? (object) [] : $data,
            'meta' => self::meta($meta),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function meta(array $meta = []): array
    {
        return [
            ...$meta,
            'generated_at' => gmdate('c'),
        ];
    }
}
