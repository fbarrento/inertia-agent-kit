<?php

declare(strict_types=1);

namespace Tests\Utils;

final class BoostResource
{
    public static function path(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }

    public static function contents(string $path): string
    {
        $contents = file_get_contents(self::path($path));

        expect($contents)->not->toBeFalse();

        return (string) $contents;
    }
}
