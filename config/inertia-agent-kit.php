<?php

declare(strict_types=1);

return [
    'adapter' => env('IAK_ADAPTER', 'react'),

    'paths' => [
        'root' => 'resources/js',
        'pages' => 'resources/js/pages',
        'features' => 'resources/js/features',
        'components_ui' => 'resources/js/components/ui',
        'components_app' => 'resources/js/components/app',
        'layouts' => 'resources/js/layouts',
        'css' => 'resources/css/iak',
        'manifest' => '.iak/manifest/iak.manifest.v1.json',
        'feedback' => '.iak/feedback',
        'runs' => '.iak/runs',
        'schemas' => '.iak/schemas',
    ],

    'generated' => [
        'type_alias' => '@/types/generated',
        'types' => 'resources/js/types/generated/index.d.ts',
        'routes' => 'resources/js/routes/generated',
        'actions' => 'resources/js/actions/generated',
    ],

    'forbidden_folders' => [
        'queries',
        'actions',
        'forms',
        'hooks',
        'composables',
    ],

    'audit' => [
        'rules' => [
            'no_forbidden_top_level_folders' => [
                'enabled' => true,
            ],
            'pages_are_route_adapters' => [
                'enabled' => true,
            ],
            'generated_types_only' => [
                'enabled' => true,
            ],
            'semantic_design_tokens' => [
                'enabled' => true,
            ],
            'no_raw_palette_or_arbitrary_values' => [
                'enabled' => true,
                'ignore_files' => [
                    'resources/css/iak/tokens.css',
                    'resources/css/iak/themes.css',
                ],
            ],
        ],
    ],

    'feedback' => [
        'path' => '.iak/feedback',
        'statuses' => [
            'pending',
            'in_progress',
            'resolved',
            'wont_fix',
            'duplicate',
        ],
    ],

    'runs' => [
        'path' => '.iak/runs',
    ],

    'boost' => [
        'policy' => 'detect',
        'publish_resources' => true,
        'never_modify_boost_owned_files' => true,
        'paths' => [
            'guidelines' => 'resources/boost/guidelines/core.blade.php',
            'skill' => 'resources/boost/skills/inertia-agent-kit/SKILL.md',
        ],
    ],

    'json_schemas' => [
        'config' => 'iak.config.v1',
        'init_result' => 'iak.init.result.v1',
        'resource_result' => 'iak.resource.result.v1',
        'audit' => 'iak.audit.v1',
        'audit_completed' => 'iak.audit.completed',
        'feedback' => 'iak.feedback.v1',
        'verify' => 'iak.verify.v1',
    ],
];
