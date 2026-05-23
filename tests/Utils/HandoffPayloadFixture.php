<?php

declare(strict_types=1);

namespace Tests\Utils;

use FilesystemIterator;
use Random\RandomException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class HandoffPayloadFixture
{
    /**
     * @throws RandomException
     */
    public static function makeBasePath(): string
    {
        $basePath = sys_get_temp_dir().'/iak-validate-action-handoff-'.bin2hex(random_bytes(6));

        mkdir($basePath, 0755, true);

        return $basePath;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validPayload(string $basePath): array
    {
        self::writeFile($basePath, 'resources/js/pages/vehicles/index.tsx', 'export default function Index() {}');
        self::writeFile($basePath, 'tests/Feature/VehicleIndexTest.php', '<?php');
        self::writeFile($basePath, '.iak/runs/run_01/audit.json', '{}');
        self::writeFile($basePath, '.iak/runs/run_01/tests.json', '{}');
        self::writeFile($basePath, '.iak/runs/run_01/handoff.json', '{}');

        return [
            'schema' => 'iak.handoff.v1',
            'runId' => 'run_01',
            'task' => 'Create vehicle index page',
            'status' => 'completed',
            'summary' => 'Vehicle index page implemented and verified.',
            'changedFiles' => [
                'page' => [[
                    'path' => 'resources/js/pages/vehicles/index.tsx',
                    'action' => 'create',
                ]],
                'test' => [[
                    'path' => 'tests/Feature/VehicleIndexTest.php',
                    'action' => 'create',
                ]],
            ],
            'evidence' => [
                'audit' => [
                    'status' => 'passed',
                    'artifact' => [
                        'kind' => 'json',
                        'path' => '.iak/runs/run_01/audit.json',
                    ],
                ],
                'tests' => [
                    'status' => 'passed',
                    'artifact' => [
                        'kind' => 'json',
                        'path' => '.iak/runs/run_01/tests.json',
                    ],
                ],
                'feedback' => [
                    'unresolved' => 0,
                ],
            ],
            'artifacts' => [
                'handoff' => [
                    'kind' => 'json',
                    'path' => '.iak/runs/run_01/handoff.json',
                ],
            ],
            'notes' => [
                'All checks passed.',
            ],
            'nextActions' => [],
            'errors' => [],
        ];
    }

    public static function removeDirectory(string $path): void
    {
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

    private static function writeFile(string $basePath, string $path, string $contents): void
    {
        $absolutePath = $basePath.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, $contents);
    }
}
