<?php

declare(strict_types=1);

namespace Tests\Utils;

final class AuditTestHelper
{
    /**
     * @return array<string, mixed>
     */
    public static function config(string $base): array
    {
        return [
            'paths' => [
                'root' => 'resources/js',
                'pages' => 'resources/js/pages',
                'features' => 'resources/js/features',
                'components_ui' => 'resources/js/components/ui',
                'components_app' => 'resources/js/components/app',
                'runs' => '.iak/runs',
                'layouts' => 'resources/js/layouts',
                'css' => 'resources/css/iak',
            ],
            'generated' => [
                'type_alias' => '@/types/generated',
                'types' => 'resources/js/types/generated/index.d.ts',
                'routes' => 'resources/js/routes/generated',
                'actions' => 'resources/js/actions/generated',
            ],
            'audit' => [
                'rules' => [
                    'no_raw_palette_or_arbitrary_values' => [
                        'ignore_files' => ['resources/css/iak/tokens.css'],
                    ],
                ],
            ],
            'forbidden_folders' => ['hooks', 'queries', 'actions'],
        ];
    }
}
