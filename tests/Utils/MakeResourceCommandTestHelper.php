<?php

declare(strict_types=1);

namespace Tests\Utils;

use Illuminate\Support\Facades\Artisan;

final class MakeResourceCommandTestHelper
{
    public static function fixtureBasePath(): string
    {
        $basePath = sys_get_temp_dir().'/iak-make-resource-'.bin2hex(random_bytes(6));

        mkdir($basePath.'/resources/js', 0755, true);

        app()->setBasePath($basePath);

        return $basePath;
    }

    public static function writeGeneratedTypes(string $basePath): void
    {
        $directory = $basePath.'/resources/js/types/generated';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($directory.'/index.d.ts', <<<'TS'
export declare namespace App {
  export namespace Data {
    export namespace Vehicles {
      export type VehicleIndexPageData = Record<string, unknown>
      export type VehicleShowPageData = Record<string, unknown>
      export type VehicleCreatePageData = Record<string, unknown>
      export type VehicleEditPageData = Record<string, unknown>
      export type VehicleListItemData = Record<string, unknown>
      export type VehicleFormData = Record<string, unknown>
      export type VehicleFiltersData = Record<string, unknown>
    }
  }
}
TS);
    }

    public static function jsonOutput(): array
    {
        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, string>
     */
    public static function stubFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/stubs/react'));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
