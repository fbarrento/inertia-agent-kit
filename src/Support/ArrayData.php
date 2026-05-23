<?php

declare(strict_types=1);

namespace InertiaAgentKit\Support;

final class ArrayData
{
    /**
     * @return array<string, mixed>
     */
    public static function stringMap(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $path
     * @return array<string, mixed>
     */
    public static function stringMapAt(array $source, array $path): array
    {
        return self::stringMap(self::valueAt($source, $path));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function stringMapList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $item = self::stringMap($item);

            if ($item !== []) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $path
     */
    public static function stringAt(array $source, array $path, string $default): string
    {
        $value = self::valueAt($source, $path);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $path
     */
    public static function intAt(array $source, array $path, int $default): int
    {
        $value = self::valueAt($source, $path);

        return is_int($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  list<string>  $path
     */
    public static function valueAt(array $source, array $path): mixed
    {
        $value = $source;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
