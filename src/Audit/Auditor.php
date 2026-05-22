<?php

declare(strict_types=1);

namespace InertiaAgentKit\Audit;

use Illuminate\Contracts\Foundation\Application;
use InertiaAgentKit\Support\ProjectPaths;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class Auditor
{
    /**
     * @var array<string, array{category: string, severity: string, summary: string}>
     */
    private const RULES = [
        'iak/design-system/no-arbitrary-value' => [
            'category' => 'design-system',
            'severity' => 'error',
            'summary' => 'Tailwind arbitrary values found outside token/config files.',
        ],
        'iak/design-system/no-raw-hex' => [
            'category' => 'design-system',
            'severity' => 'error',
            'summary' => 'Raw hex colors found outside token files.',
        ],
        'iak/design-system/no-primitive-color' => [
            'category' => 'design-system',
            'severity' => 'error',
            'summary' => 'Primitive Tailwind color utilities found.',
        ],
        'iak/role/no-top-level-behavior-folder' => [
            'category' => 'role',
            'severity' => 'error',
            'summary' => 'Forbidden top-level behavior folders found.',
        ],
        'iak/stories/required-ui' => [
            'category' => 'stories',
            'severity' => 'error',
            'summary' => 'UI components are missing required stories.',
        ],
        'iak/stories/required-app' => [
            'category' => 'stories',
            'severity' => 'error',
            'summary' => 'App components are missing required stories.',
        ],
        'iak/stories/required-feature' => [
            'category' => 'stories',
            'severity' => 'error',
            'summary' => 'Feature table/form components are missing required stories.',
        ],
        'iak/types/generated-contract-import-required' => [
            'category' => 'types',
            'severity' => 'error',
            'summary' => 'Feature type files must import generated backend contracts.',
        ],
    ];

    /**
     * @var list<string>
     */
    private const COLOR_PREFIXES = [
        'accent',
        'bg',
        'border',
        'caret',
        'decoration',
        'divide',
        'fill',
        'from',
        'outline',
        'placeholder',
        'ring',
        'stroke',
        'text',
        'to',
        'via',
    ];

    /**
     * @var list<string>
     */
    private const COLOR_NAMES = [
        'amber',
        'blue',
        'cyan',
        'emerald',
        'fuchsia',
        'gray',
        'green',
        'indigo',
        'lime',
        'neutral',
        'orange',
        'pink',
        'purple',
        'red',
        'rose',
        'sky',
        'slate',
        'stone',
        'teal',
        'violet',
        'yellow',
        'zinc',
    ];

    /**
     * @var list<string>
     */
    private const SKIPPED_PATHS = [
        '.git',
        '.iak/feedback',
        '.iak/runs',
        'bootstrap/cache',
        'node_modules',
        'public/build',
        'storage',
        'vendor',
    ];

    private readonly ProjectPaths $paths;

    public function __construct(Application $app)
    {
        $this->paths = new ProjectPaths($app);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{
     *     status: string,
     *     totals: array<string, int>,
     *     checks: list<array<string, mixed>>,
     *     violations: list<array<string, mixed>>,
     *     nextActions: list<array<string, mixed>>
     * }
     */
    public function run(array $config): array
    {
        $stats = $this->emptyStats();
        $violations = [];

        $this->scanDesignSystem($config, $stats, $violations);
        $this->scanForbiddenFolders($config, $stats, $violations);
        $this->scanStories($config, $stats, $violations);
        $this->scanGeneratedTypeImports($config, $stats, $violations);

        $violations = $this->sortAndIdentifyViolations($violations);
        $checks = $this->buildChecks($stats, $violations);
        $totals = $this->buildTotals($checks, $violations);

        return [
            'status' => $totals['errors'] > 0 ? 'failed' : 'passed',
            'totals' => $totals,
            'checks' => $checks,
            'violations' => $violations,
            'nextActions' => $this->buildNextActions($violations),
        ];
    }

    /**
     * @return array<string, array{filesScanned: int}>
     */
    private function emptyStats(): array
    {
        $stats = [];

        foreach (array_keys(self::RULES) as $rule) {
            $stats[$rule] = ['filesScanned' => 0];
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, array{filesScanned: int}> $stats
     * @param list<array<string, mixed>> $violations
     */
    private function scanDesignSystem(array $config, array &$stats, array &$violations): void
    {
        $ignoredFiles = $this->styleIgnoredFiles($config);
        $files = array_values(array_filter(
            $this->collectFiles($this->frontendRoots($config), fn (string $path): bool => $this->isScannableFrontendFile($path)),
            fn (array $file): bool => ! isset($ignoredFiles[$file['path']]) && ! $this->isGeneratedPath($file['path'], $config)
        ));

        foreach ([
            'iak/design-system/no-arbitrary-value',
            'iak/design-system/no-raw-hex',
            'iak/design-system/no-primitive-color',
        ] as $rule) {
            $stats[$rule]['filesScanned'] = count($files);
        }

        foreach ($files as $file) {
            $contents = $this->read($file['absolute']);
            $source = $this->stripComments($contents);

            $this->scanArbitraryValues($file['path'], $source, $violations);
            $this->scanRawHex($file['path'], $source, $violations);
            $this->scanPrimitiveColorUtilities($file['path'], $source, $violations);
        }
    }

    /**
     * @param list<array<string, mixed>> $violations
     */
    private function scanArbitraryValues(string $path, string $source, array &$violations): void
    {
        preg_match_all('/(?<![A-Za-z0-9_:-])!?[A-Za-z][A-Za-z0-9_:\\/.-]*-\[[^\]\s"\'`<>]+\](?![A-Za-z0-9_:-])/', $source, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$hit, $offset]) {
            [$line, $column] = $this->lineColumn($source, $offset);

            $this->addViolation(
                $violations,
                'iak/design-system/no-arbitrary-value',
                $path,
                $line,
                $column,
                $hit,
                'Tailwind arbitrary values must be represented by semantic design-system tokens.',
                [
                    'kind' => 'replace',
                    'summary' => 'Replace the arbitrary utility with a semantic design-system utility or token.',
                    'current' => $hit,
                    'preferred' => 'ds-* semantic utility',
                    'applicability' => 'manual',
                ]
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $violations
     */
    private function scanRawHex(string $path, string $source, array &$violations): void
    {
        preg_match_all('/(?<![A-Za-z0-9_])#(?:[0-9A-Fa-f]{3,4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})(?![0-9A-Fa-f])/', $source, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$hit, $offset]) {
            [$line, $column] = $this->lineColumn($source, $offset);

            $this->addViolation(
                $violations,
                'iak/design-system/no-raw-hex',
                $path,
                $line,
                $column,
                $hit,
                'Raw hex colors are allowed only in configured primitive token files.',
                [
                    'kind' => 'replace',
                    'summary' => 'Use a semantic design-system utility or add a token mapping.',
                    'current' => $hit,
                    'preferred' => 'bg-ds-surface',
                    'applicability' => 'manual',
                ]
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $violations
     */
    private function scanPrimitiveColorUtilities(string $path, string $source, array &$violations): void
    {
        $prefixes = implode('|', array_map(static fn (string $value): string => preg_quote($value, '/'), self::COLOR_PREFIXES));
        $colors = implode('|', array_map(static fn (string $value): string => preg_quote($value, '/'), self::COLOR_NAMES));
        $pattern = '/(?<![A-Za-z0-9_-])!?(?:[a-z0-9-]+:)*(?:'.$prefixes.')-(?:(?:'.$colors.')-(?:50|100|200|300|400|500|600|700|800|900|950)|white|black)(?:\/(?:[0-9]{1,3}|\[[^\]]+\]))?(?![A-Za-z0-9_-])/';

        preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$hit, $offset]) {
            [$line, $column] = $this->lineColumn($source, $offset);

            $this->addViolation(
                $violations,
                'iak/design-system/no-primitive-color',
                $path,
                $line,
                $column,
                $hit,
                'Primitive Tailwind color utilities must be replaced by semantic design-system utilities.',
                [
                    'kind' => 'replace',
                    'summary' => 'Replace the primitive color utility with a semantic color utility.',
                    'current' => $hit,
                    'preferred' => 'text-ds-muted',
                    'applicability' => 'manual',
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, array{filesScanned: int}> $stats
     * @param list<array<string, mixed>> $violations
     */
    private function scanForbiddenFolders(array $config, array &$stats, array &$violations): void
    {
        $rule = 'iak/role/no-top-level-behavior-folder';
        $root = $this->configuredPath($config, 'root', 'resources/js');
        $folders = $this->stringList($config['forbidden_folders'] ?? [], ['queries', 'actions', 'forms', 'hooks', 'composables']);

        sort($folders, SORT_STRING);

        foreach ($folders as $folder) {
            $folderPath = $this->joinRelative($root, $folder);
            $absolute = $this->paths->absolute($folderPath);

            if (! is_dir($absolute)) {
                continue;
            }

            $files = $this->collectFiles([$folderPath], fn (string $path): bool => ! $this->isGeneratedPath($path, $config));
            $stats[$rule]['filesScanned'] += count($files);

            foreach ($files as $file) {
                $this->addViolation(
                    $violations,
                    $rule,
                    $file['path'],
                    1,
                    1,
                    $folderPath,
                    'Behavior folders are not allowed as top-level frontend folders unless they are configured generated output.',
                    [
                        'kind' => 'move',
                        'summary' => 'Move behavior into a feature folder or configured generated output.',
                        'current' => $file['path'],
                        'preferred' => $this->joinRelative($root, 'features/<resource>'),
                        'applicability' => 'manual',
                    ]
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, array{filesScanned: int}> $stats
     * @param list<array<string, mixed>> $violations
     */
    private function scanStories(array $config, array &$stats, array &$violations): void
    {
        $this->scanStoryScope(
            'iak/stories/required-ui',
            $this->configuredPath($config, 'components_ui', 'resources/js/components/ui'),
            fn (string $path): bool => $this->isStoryRequiredComponent($path),
            $stats,
            $violations
        );

        $this->scanStoryScope(
            'iak/stories/required-app',
            $this->configuredPath($config, 'components_app', 'resources/js/components/app'),
            fn (string $path): bool => $this->isStoryRequiredComponent($path),
            $stats,
            $violations
        );

        $this->scanStoryScope(
            'iak/stories/required-feature',
            $this->configuredPath($config, 'features', 'resources/js/features'),
            fn (string $path): bool => $this->isFeatureStoryRequiredComponent($path),
            $stats,
            $violations
        );
    }

    /**
     * @param callable(string): bool $predicate
     * @param array<string, array{filesScanned: int}> $stats
     * @param list<array<string, mixed>> $violations
     */
    private function scanStoryScope(string $rule, string $root, callable $predicate, array &$stats, array &$violations): void
    {
        $files = $this->collectFiles([$root], $predicate);
        $stats[$rule]['filesScanned'] = count($files);

        foreach ($files as $file) {
            if ($this->hasColocatedStory($file['path'])) {
                continue;
            }

            $expectedStory = $this->expectedStoryPath($file['path']);

            $this->addViolation(
                $violations,
                $rule,
                $file['path'],
                1,
                1,
                $expectedStory,
                'Required component story is missing.',
                [
                    'kind' => 'create',
                    'summary' => 'Create the required colocated story file.',
                    'current' => $file['path'],
                    'preferred' => $expectedStory,
                    'applicability' => 'manual',
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, array{filesScanned: int}> $stats
     * @param list<array<string, mixed>> $violations
     */
    private function scanGeneratedTypeImports(array $config, array &$stats, array &$violations): void
    {
        $rule = 'iak/types/generated-contract-import-required';
        $featuresRoot = $this->configuredPath($config, 'features', 'resources/js/features');
        $files = $this->collectFiles([$featuresRoot], fn (string $path): bool => str_ends_with($path, '.types.ts'));
        $stats[$rule]['filesScanned'] = count($files);

        foreach ($files as $file) {
            $contents = $this->read($file['absolute']);

            if ($this->hasGeneratedContractImport($contents, $file['path'], $config)) {
                continue;
            }

            $preferred = $this->generatedTypeAlias($config);

            $this->addViolation(
                $violations,
                $rule,
                $file['path'],
                1,
                1,
                $preferred,
                'Feature type files must import generated backend contracts instead of copying DTO shapes.',
                [
                    'kind' => 'import',
                    'summary' => 'Import generated backend contracts and compose local aliases from them.',
                    'current' => $file['path'],
                    'preferred' => "import type { App } from '{$preferred}'",
                    'applicability' => 'manual',
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private function frontendRoots(array $config): array
    {
        $paths = $this->pathsConfig($config);
        $roots = [];

        foreach (['root', 'pages', 'features', 'components_ui', 'components_app', 'layouts', 'css'] as $key) {
            if (isset($paths[$key]) && is_string($paths[$key]) && $paths[$key] !== '') {
                $roots[] = $this->paths->relative($paths[$key]);
            }
        }

        $roots[] = 'resources/css';
        $roots[] = 'resources/views';

        return array_values(array_unique(array_filter($roots, static fn (string $path): bool => $path !== '')));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, true>
     */
    private function styleIgnoredFiles(array $config): array
    {
        $rules = $config['audit']['rules'] ?? [];
        $ruleConfig = is_array($rules) && isset($rules['no_raw_palette_or_arbitrary_values']) && is_array($rules['no_raw_palette_or_arbitrary_values'])
            ? $rules['no_raw_palette_or_arbitrary_values']
            : [];

        $files = $this->stringList($ruleConfig['ignore_files'] ?? [], [
            'resources/css/iak/tokens.css',
            'resources/css/iak/themes.css',
        ]);

        $ignored = [];

        foreach ($files as $file) {
            $ignored[$this->paths->relative($file)] = true;
        }

        return $ignored;
    }

    /**
     * @param list<string> $roots
     * @param null|callable(string): bool $predicate
     *
     * @return list<array{path: string, absolute: string}>
     */
    private function collectFiles(array $roots, ?callable $predicate = null): array
    {
        $files = [];

        foreach ($roots as $root) {
            $absoluteRoot = $this->paths->absolute($root);

            if (! is_dir($absoluteRoot)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteRoot, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $path = $this->paths->relative($file->getPathname());

                if ($this->isSkippedPath($path) || ($predicate !== null && ! $predicate($path))) {
                    continue;
                }

                $files[$path] = [
                    'path' => $path,
                    'absolute' => $file->getPathname(),
                ];
            }
        }

        ksort($files, SORT_STRING);

        return array_values($files);
    }

    private function isSkippedPath(string $path): bool
    {
        foreach (self::SKIPPED_PATHS as $skippedPath) {
            if ($path === $skippedPath || str_starts_with($path, $skippedPath.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function isGeneratedPath(string $path, array $config): bool
    {
        $generated = isset($config['generated']) && is_array($config['generated']) ? $config['generated'] : [];
        $paths = [];

        foreach (['types', 'routes', 'actions'] as $key) {
            if (isset($generated[$key]) && is_string($generated[$key]) && $generated[$key] !== '') {
                $paths[] = $this->paths->relative($generated[$key]);
            }
        }

        foreach ($paths as $generatedPath) {
            if ($path === $generatedPath || str_starts_with($path, rtrim($generatedPath, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    private function isScannableFrontendFile(string $path): bool
    {
        foreach (['.blade.php', '.css', '.html', '.js', '.jsx', '.less', '.mdx', '.sass', '.scss', '.ts', '.tsx', '.vue'] as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isStoryRequiredComponent(string $path): bool
    {
        if (! $this->isComponentLikeFile($path)) {
            return false;
        }

        return ! $this->isIgnoredComponentLikeFile($path);
    }

    private function isFeatureStoryRequiredComponent(string $path): bool
    {
        if (! $this->isStoryRequiredComponent($path)) {
            return false;
        }

        $basename = $this->basenameWithoutExtension($path);

        return str_ends_with($basename, '-table') || str_ends_with($basename, '-form');
    }

    private function isComponentLikeFile(string $path): bool
    {
        foreach (['.jsx', '.tsx', '.vue'] as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isIgnoredComponentLikeFile(string $path): bool
    {
        $basename = basename($path);

        return str_starts_with($basename, 'index.')
            || str_contains($basename, '.stories.')
            || str_contains($basename, '.test.')
            || str_contains($basename, '.spec.')
            || str_contains($basename, '.fixture.')
            || str_contains($basename, '.fixtures.')
            || str_contains($basename, '.generated.')
            || str_contains($basename, '.types.');
    }

    private function hasColocatedStory(string $path): bool
    {
        $directory = dirname($path);
        $basename = $this->basenameWithoutExtension($path);
        $absoluteDirectory = $this->paths->absolute($directory);
        $matches = glob($absoluteDirectory.DIRECTORY_SEPARATOR.$basename.'.stories.*') ?: [];

        foreach ($matches as $match) {
            if (is_file($match)) {
                return true;
            }
        }

        return false;
    }

    private function expectedStoryPath(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $this->joinRelative(dirname($path), $this->basenameWithoutExtension($path).'.stories.'.$extension);
    }

    private function basenameWithoutExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasGeneratedContractImport(string $contents, string $filePath, array $config): bool
    {
        foreach ($this->generatedImportSpecifiers($filePath, $config) as $specifier) {
            $quoted = preg_quote($specifier, '/');

            if (preg_match('/import\s+(?:type\s+)?[\s\S]*?\s+from\s*[\'"]'.$quoted.'[\'"]/m', $contents) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private function generatedImportSpecifiers(string $filePath, array $config): array
    {
        $specifiers = [$this->generatedTypeAlias($config)];
        $generated = isset($config['generated']) && is_array($config['generated']) ? $config['generated'] : [];
        $generatedTypes = isset($generated['types']) && is_string($generated['types'])
            ? $this->paths->relative($generated['types'])
            : 'resources/js/types/generated/index.d.ts';

        $generatedTypesWithoutExtension = preg_replace('/(?:\.d)?\.ts$/', '', $generatedTypes) ?? $generatedTypes;
        $generatedDirectory = str_ends_with($generatedTypesWithoutExtension, '/index')
            ? dirname($generatedTypesWithoutExtension)
            : $generatedTypesWithoutExtension;
        $fromDirectory = dirname($filePath);
        $relativeDirectory = $this->relativeBetween($fromDirectory, $generatedDirectory);
        $relativeFile = $this->relativeBetween($fromDirectory, $generatedTypesWithoutExtension);

        $specifiers[] = $relativeDirectory;
        $specifiers[] = $relativeDirectory.'/index';
        $specifiers[] = $relativeFile;

        return array_values(array_unique(array_filter($specifiers, static fn (string $specifier): bool => $specifier !== '')));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function generatedTypeAlias(array $config): string
    {
        $generated = isset($config['generated']) && is_array($config['generated']) ? $config['generated'] : [];

        return isset($generated['type_alias']) && is_string($generated['type_alias']) && $generated['type_alias'] !== ''
            ? rtrim($generated['type_alias'], '/')
            : '@/types/generated';
    }

    private function relativeBetween(string $fromDirectory, string $toPath): string
    {
        $from = array_values(array_filter(explode('/', trim($fromDirectory, '/')), static fn (string $part): bool => $part !== ''));
        $to = array_values(array_filter(explode('/', trim($toPath, '/')), static fn (string $part): bool => $part !== ''));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        $relative = implode('/', [
            ...array_fill(0, count($from), '..'),
            ...$to,
        ]);

        if ($relative === '') {
            return '.';
        }

        return str_starts_with($relative, '.') ? $relative : './'.$relative;
    }

    /**
     * @param list<array<string, mixed>> $violations
     */
    private function addViolation(
        array &$violations,
        string $rule,
        string $file,
        int $line,
        int $column,
        string $hit,
        string $message,
        array $suggestion
    ): void {
        $meta = self::RULES[$rule];
        [$role, $resource] = $this->roleAndResource($file);

        $violations[] = [
            'id' => '',
            'rule' => $rule,
            'category' => $meta['category'],
            'severity' => $meta['severity'],
            'confidence' => 'high',
            'file' => $file,
            'line' => $line,
            'column' => $column,
            'endLine' => $line,
            'endColumn' => $column + max(strlen($hit), 1),
            'role' => $role,
            'resource' => $resource,
            'hit' => $hit,
            'message' => $message,
            'suggestion' => $suggestion,
            'docs' => ['docs/inertia-agent-kit/laravel-package-audit-command.md'],
            'fingerprint' => 'sha256:'.hash('sha256', implode('|', [$rule, $file, (string) $line, (string) $column, $hit])),
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function roleAndResource(string $file): array
    {
        if (preg_match('#^resources/js/features/([^/]+)/#', $file, $matches) === 1) {
            return ['feature', $matches[1]];
        }

        if (str_starts_with($file, 'resources/js/pages/')) {
            return ['page', null];
        }

        if (str_starts_with($file, 'resources/js/components/ui/')) {
            return ['component-ui', null];
        }

        if (str_starts_with($file, 'resources/js/components/app/')) {
            return ['component-app', null];
        }

        if (str_starts_with($file, 'resources/js/layouts/')) {
            return ['layout', null];
        }

        return ['source', null];
    }

    /**
     * @param list<array<string, mixed>> $violations
     *
     * @return list<array<string, mixed>>
     */
    private function sortAndIdentifyViolations(array $violations): array
    {
        usort($violations, static fn (array $left, array $right): int => [
            $left['file'],
            $left['line'],
            $left['column'],
            $left['rule'],
            $left['hit'],
        ] <=> [
            $right['file'],
            $right['line'],
            $right['column'],
            $right['rule'],
            $right['hit'],
        ]);

        foreach ($violations as $index => $violation) {
            $violations[$index]['id'] = sprintf('vio_%03d', $index + 1);
        }

        return $violations;
    }

    /**
     * @param array<string, array{filesScanned: int}> $stats
     * @param list<array<string, mixed>> $violations
     *
     * @return list<array<string, mixed>>
     */
    private function buildChecks(array $stats, array $violations): array
    {
        $counts = [];

        foreach ($violations as $violation) {
            $rule = (string) $violation['rule'];
            $counts[$rule] = ($counts[$rule] ?? 0) + 1;
        }

        $checks = [];

        foreach (self::RULES as $rule => $meta) {
            $findings = $counts[$rule] ?? 0;

            $checks[] = [
                'id' => $rule,
                'category' => $meta['category'],
                'status' => $findings > 0 ? 'failed' : 'passed',
                'severity' => $meta['severity'],
                'summary' => $meta['summary'],
                'filesScanned' => $stats[$rule]['filesScanned'],
                'findings' => $findings,
                'durationMs' => 0,
            ];
        }

        return $checks;
    }

    /**
     * @param list<array<string, mixed>> $checks
     * @param list<array<string, mixed>> $violations
     *
     * @return array<string, int>
     */
    private function buildTotals(array $checks, array $violations): array
    {
        $failed = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'failed'));
        $blocked = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'blocked'));
        $errors = count(array_filter($violations, static fn (array $violation): bool => $violation['severity'] === 'error'));
        $warnings = count(array_filter($violations, static fn (array $violation): bool => $violation['severity'] === 'warning'));

        return [
            'checks' => count($checks),
            'passed' => count($checks) - $failed - $blocked,
            'failed' => $failed,
            'blocked' => $blocked,
            'findings' => count($violations),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param list<array<string, mixed>> $violations
     *
     * @return list<array<string, mixed>>
     */
    private function buildNextActions(array $violations): array
    {
        return array_map(static fn (array $violation): array => [
            'type' => 'fix',
            'summary' => $violation['suggestion']['summary'],
            'rule' => $violation['rule'],
            'file' => $violation['file'],
            'line' => $violation['line'],
        ], $violations);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function pathsConfig(array $config): array
    {
        return isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configuredPath(array $config, string $key, string $default): string
    {
        $paths = $this->pathsConfig($config);

        return isset($paths[$key]) && is_string($paths[$key]) && $paths[$key] !== ''
            ? $this->paths->relative($paths[$key])
            : $default;
    }

    /**
     * @param mixed $value
     * @param list<string> $default
     *
     * @return list<string>
     */
    private function stringList(mixed $value, array $default): array
    {
        if (! is_array($value)) {
            return $default;
        }

        $strings = array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));

        return $strings === [] ? $default : $strings;
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    private function stripComments(string $contents): string
    {
        $contents = preg_replace_callback('/\/\*.*?\*\//s', fn (array $match): string => $this->blankPreservingLines($match[0]), $contents) ?? $contents;

        return preg_replace_callback('/(^|[^:])\/\/[^\r\n]*/m', function (array $match): string {
            return $match[1].str_repeat(' ', strlen($match[0]) - strlen($match[1]));
        }, $contents) ?? $contents;
    }

    private function blankPreservingLines(string $text): string
    {
        return preg_replace('/[^\r\n]/', ' ', $text) ?? str_repeat(' ', strlen($text));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function lineColumn(string $contents, int $offset): array
    {
        $before = substr($contents, 0, $offset);
        $line = substr_count($before, "\n") + 1;
        $lastNewline = strrpos($before, "\n");
        $column = $lastNewline === false ? strlen($before) + 1 : strlen($before) - $lastNewline;

        return [$line, $column];
    }

    private function joinRelative(string ...$parts): string
    {
        return implode('/', array_values(array_filter(array_map(static fn (string $part): string => trim($part, '/'), $parts), static fn (string $part): bool => $part !== '')));
    }
}
