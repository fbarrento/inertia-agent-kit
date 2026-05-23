<?php

declare(strict_types=1);

namespace Tests\Utils;

final class ScaffoldingTestHelper
{
    public static function writeGeneratedTypes(string $path, string $singularStudly): void
    {
        mkdir($path.'/resources/js/types/generated', 0755, true);

        file_put_contents($path.'/resources/js/types/generated/index.d.ts', <<<TS
export namespace App {
  export namespace Data {
    export namespace {$singularStudly} {
      export type {$singularStudly}IndexPageData = never
      export type {$singularStudly}ShowPageData = never
      export type {$singularStudly}CreatePageData = never
      export type {$singularStudly}EditPageData = never
      export type {$singularStudly}ListItemData = never
      export type {$singularStudly}FormData = never
      export type {$singularStudly}FiltersData = never
    }
  }
}
TS);
    }
}
