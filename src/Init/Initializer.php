<?php

declare(strict_types=1);

namespace InertiaAgentKit\Init;

use Illuminate\Contracts\Foundation\Application;
use InertiaAgentKit\Support\Files;
use InertiaAgentKit\Support\ProjectPaths;
use JsonException;
use RuntimeException;
use Throwable;

final class Initializer
{
    private const INIT_SCHEMA = 'iak.init.result.v1';

    private const MANIFEST_SCHEMA = 'iak.manifest.v1';

    private const ADAPTER = 'react';

    private readonly ProjectPaths $paths;

    private readonly Files $files;

    public function __construct(private readonly Application $app, ?ProjectPaths $paths = null, ?Files $files = null)
    {
        $this->paths = $paths ?? new ProjectPaths($app);
        $this->files = $files ?? new Files($this->paths);
    }

    /**
     * @return array{exitCode: int, payload: array<string, mixed>}
     */
    public function run(string $adapter, bool $force): array
    {
        $adapter = strtolower(trim($adapter) ?: self::ADAPTER);
        $createdAt = gmdate('c');
        $runId = $this->runId();
        $project = $this->detectProject($adapter);
        $boost = $this->boostPayload((bool) $project['boostInstalled']);

        if ($adapter !== self::ADAPTER) {
            return $this->failed($runId, $createdAt, $adapter, $project, $boost, [], [[
                'code' => 'unsupported_adapter',
                'message' => "The [{$adapter}] adapter is not supported by iak:init v1.",
                'context' => [
                    'adapter' => $adapter,
                    'supported' => [self::ADAPTER],
                ],
            ]], 2);
        }

        $reports = [];

        try {
            $config = $this->baseConfig($adapter);

            $this->ensureDirectory('config', 'directory', true, $reports);
            $this->writeSource('config/inertia-agent-kit.php', $this->packageConfigContents(), 'config', $reports);
            $this->writeSource('iak.config.json', $this->json($config), 'config', $reports);

            foreach (['.iak', '.iak/state', '.iak/manifest', '.iak/schemas', '.iak/feedback', '.iak/runs', '.iak/rules'] as $directory) {
                $this->ensureDirectory($directory, 'directory', false, $reports);
            }

            $runtime = $this->json($this->runtimeConfig($config, $project, $boost));
            $manifest = $this->json($this->manifest($config, $project, $boost));
            $initSchema = $this->json($this->schema(self::INIT_SCHEMA, [
                'schema',
                'event',
                'status',
                'files',
                'artifacts',
                'manifest',
                'boost',
                'nextActions',
                'errors',
                'meta',
            ]));
            $manifestSchema = $this->json($this->schema(self::MANIFEST_SCHEMA, [
                'schema',
                'status',
                'project',
                'adapter',
                'conventions',
                'commands',
                'schemas',
                'boost',
                'artifacts',
            ]));
            $rules = $this->rulesMarkdown((bool) $project['boostInstalled']);
            $hashes = [
                '.iak/config.json' => hash('sha256', $runtime),
                '.iak/manifest/iak.manifest.v1.json' => hash('sha256', $manifest),
                '.iak/schemas/iak.init.result.v1.schema.json' => hash('sha256', $initSchema),
                '.iak/schemas/iak.manifest.v1.schema.json' => hash('sha256', $manifestSchema),
                '.iak/rules/inertia-agent-kit.md' => hash('sha256', $rules),
            ];
            $state = $this->json([
                'schema' => 'iak.init.state.v1',
                'commandSchema' => self::INIT_SCHEMA,
                'iakVersion' => $project['iakVersion'],
                'laravel' => $project['laravel'],
                'inertia' => $project['inertia'],
                'boostInstalled' => $project['boostInstalled'],
                'generatedFileHashes' => $hashes,
            ]);

            $this->writeGenerated('.iak/config.json', $runtime, 'runtime_config', $force, $reports);
            $this->writeGenerated('.iak/state/init.json', $state, 'state', $force, $reports);
            $this->writeGenerated('.iak/manifest/iak.manifest.v1.json', $manifest, 'manifest', $force, $reports);
            $this->writeGenerated('.iak/schemas/iak.init.result.v1.schema.json', $initSchema, 'schema', $force, $reports);
            $this->writeGenerated('.iak/schemas/iak.manifest.v1.schema.json', $manifestSchema, 'schema', $force, $reports);
            $this->writeGenerated('.iak/rules/inertia-agent-kit.md', $rules, 'rules', $force, $reports);
        } catch (Throwable $exception) {
            $expected = $exception instanceof RuntimeException || $exception instanceof JsonException;

            return $this->failed($runId, $createdAt, $adapter, $project, $boost, $reports, [[
                'code' => $expected ? 'init_filesystem_error' : 'init_internal_error',
                'message' => $exception->getMessage(),
                'context' => [],
            ]], $expected ? 2 : 4);
        }

        return [
            'exitCode' => 0,
            'payload' => [
                'schema' => self::INIT_SCHEMA,
                'event' => 'iak.init.completed.v1',
                'status' => 'completed',
                'summary' => 'IAK initialized for Laravel Inertia React.',
                'runId' => $runId,
                'project' => $this->projectPayload($project),
                'files' => $reports,
                'manifest' => $this->manifestPayload('valid'),
                'boost' => $boost,
                'artifacts' => $this->artifactsPayload(),
                'nextActions' => $this->nextActions((bool) $project['boostInstalled']),
                'errors' => [],
                'meta' => $this->meta($createdAt, $adapter),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $reports
     */
    private function ensureDirectory(string $path, string $kind, bool $sourceControlled, array &$reports): void
    {
        $absolute = $this->paths->absolute($path);

        if (is_dir($absolute)) {
            $reports[] = $this->fileReport($path, $kind, 'unchanged', $sourceControlled);

            return;
        }

        if (file_exists($absolute)) {
            throw new RuntimeException("Path [{$path}] exists and is not a directory.");
        }

        $this->files->ensureDirectory($path);
        $reports[] = $this->fileReport($path, $kind, 'created', $sourceControlled);
    }

    /**
     * @param list<array<string, mixed>> $reports
     */
    private function writeSource(string $path, string $contents, string $kind, array &$reports): void
    {
        $this->writeFile($path, $contents, $kind, true, false, false, $reports);
    }

    /**
     * @param list<array<string, mixed>> $reports
     */
    private function writeGenerated(string $path, string $contents, string $kind, bool $force, array &$reports): void
    {
        $this->writeFile($path, $contents, $kind, false, true, $force, $reports);
    }

    /**
     * @param list<array<string, mixed>> $reports
     */
    private function writeFile(string $path, string $contents, string $kind, bool $sourceControlled, bool $generated, bool $force, array &$reports): void
    {
        $absolute = $this->paths->absolute($path);

        if (is_dir($absolute)) {
            throw new RuntimeException("Path [{$path}] exists and is not a file.");
        }

        if (! is_file($absolute)) {
            $this->files->write($path, $contents);
            $reports[] = $this->fileReport($path, $kind, 'created', $sourceControlled);

            return;
        }

        $current = file_get_contents($absolute);

        if ($current === false) {
            throw new RuntimeException("Unable to read file [{$path}].");
        }

        if ($current === $contents) {
            $reports[] = $this->fileReport($path, $kind, 'unchanged', $sourceControlled);

            return;
        }

        if ($generated && $force) {
            $this->files->write($path, $contents);
            $reports[] = $this->fileReport($path, $kind, 'updated', $sourceControlled);

            return;
        }

        $reports[] = [
            ...$this->fileReport($path, $kind, 'skipped', $sourceControlled),
            'reason' => 'existing_content_preserved',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileReport(string $path, string $kind, string $action, bool $sourceControlled): array
    {
        return [
            'path' => $this->paths->toUnix($path),
            'kind' => $kind,
            'action' => $action,
            'sourceControlled' => $sourceControlled,
        ];
    }

    private function packageConfigContents(): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/config/inertia-agent-kit.php');

        if ($contents === false) {
            throw new RuntimeException('Unable to read package config stub.');
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseConfig(string $adapter): array
    {
        $root = $this->stringConfig('paths.root', 'resources/js');

        return [
            'schema' => $this->stringConfig('json_schemas.config', 'iak.config.v1'),
            'project' => [
                'framework' => 'laravel',
                'inertia' => true,
                'adapter' => $this->adapterId($adapter),
            ],
            'paths' => [
                'root' => $root,
                'pages' => $this->stringConfig('paths.pages', "{$root}/pages"),
                'features' => $this->stringConfig('paths.features', "{$root}/features"),
                'componentsUi' => $this->stringConfig('paths.components_ui', "{$root}/components/ui"),
                'componentsApp' => $this->stringConfig('paths.components_app', "{$root}/components/app"),
                'layouts' => $this->stringConfig('paths.layouts', "{$root}/layouts"),
                'typesGenerated' => $this->stringConfig('generated.type_alias', '@/types/generated'),
                'css' => $this->stringConfig('paths.css', 'resources/css/iak'),
                'manifest' => '.iak/manifest/iak.manifest.v1.json',
                'feedback' => '.iak/feedback',
                'runs' => '.iak/runs',
                'schemas' => '.iak/schemas',
            ],
            'generated' => [
                'types' => $this->stringConfig('generated.types', "{$root}/types/generated/index.d.ts"),
                'routes' => $this->stringConfig('generated.routes', "{$root}/routes/generated"),
                'actions' => $this->stringConfig('generated.actions', "{$root}/actions/generated"),
            ],
            'conventions' => [
                'pages' => 'route-adapters-only',
                'features' => 'resource-local-ui',
                'formatting' => 'backend-owned',
                'translations' => 'backend-owned',
                'styling' => 'semantic-design-system-tokens-only',
            ],
            'commands' => $this->commandsPayload(),
            'meta' => [
                'generatedBy' => 'inertia-agent-kit',
                'version' => 1,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $project
     * @param array<string, mixed> $boost
     *
     * @return array<string, mixed>
     */
    private function runtimeConfig(array $config, array $project, array $boost): array
    {
        return [
            'schema' => 'iak.runtime.config.v1',
            'project' => $this->projectPayload($project),
            'config' => $config,
            'paths' => [
                'config' => 'iak.config.json',
                'manifest' => '.iak/manifest/iak.manifest.v1.json',
                'schemas' => '.iak/schemas',
                'feedback' => '.iak/feedback',
                'runs' => '.iak/runs',
                'rules' => '.iak/rules/inertia-agent-kit.md',
            ],
            'boost' => $boost,
            'commands' => $this->commandsPayload(),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $project
     * @param array<string, mixed> $boost
     *
     * @return array<string, mixed>
     */
    private function manifest(array $config, array $project, array $boost): array
    {
        return [
            'schema' => self::MANIFEST_SCHEMA,
            'id' => 'manifest_'.substr(hash('sha256', $this->paths->basePath().'|'.$project['renderer']), 0, 16),
            'status' => 'valid',
            'summary' => 'Laravel Inertia React project with IAK initialized.',
            'project' => [
                'name' => $project['name'],
                'root' => '.',
                'laravel' => $project['laravel'],
                'inertia' => $project['inertia'],
                'renderer' => $project['renderer'],
                'typescript' => $project['typescript'],
            ],
            'adapter' => [
                'id' => $this->adapterId((string) $project['renderer']),
                'version' => $project['iakVersion'],
            ],
            'conventions' => [
                'pagesAreRouteAdapters' => true,
                'featureRoot' => $config['paths']['features'],
                'pageRoot' => $config['paths']['pages'],
                'generatedTypes' => $config['paths']['typesGenerated'],
                'backendOwnsFormatting' => true,
            ],
            'resources' => [],
            'commands' => $this->commandsPayload(),
            'schemas' => [
                'init' => '.iak/schemas/iak.init.result.v1.schema.json',
                'manifest' => '.iak/schemas/iak.manifest.v1.schema.json',
            ],
            'boost' => [
                'installed' => $boost['installed'],
                'useBoostFor' => $this->boostUses(),
                'useIakFor' => $this->iakUses(),
            ],
            'artifacts' => $this->artifactsPayload(),
        ];
    }

    /**
     * @param list<string> $required
     *
     * @return array<string, mixed>
     */
    private function schema(string $schema, array $required): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => $required,
            'properties' => [
                'schema' => ['const' => $schema],
            ],
        ];
    }

    private function rulesMarkdown(bool $boostInstalled): string
    {
        $boostLine = $boostInstalled
            ? 'Use Laravel Boost for Laravel docs, app info, URLs, logs, browser logs, and database/schema inspection.'
            : 'Laravel Boost is not detected; install it for Laravel docs, app info, URLs, logs, browser logs, and database/schema inspection.';

        return <<<MARKDOWN
# Inertia Agent Kit Rules

{$boostLine}

Use IAK for manifest reads, Inertia resource conventions, design tokens, component audits, verification, feedback, and JSON handoff.

Do not edit Boost-owned files such as CLAUDE.md, AGENTS.md, .mcp.json, or boost.json from IAK commands.

MARKDOWN;
    }

    /**
     * @return array<string, mixed>
     */
    private function detectProject(string $adapter): array
    {
        $composer = $this->readJson('composer.json');
        $package = $this->readJson('package.json');
        $dependencies = [
            ...(is_array($composer['require'] ?? null) ? $composer['require'] : []),
            ...(is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : []),
        ];
        $nodeDependencies = [
            ...(is_array($package['dependencies'] ?? null) ? $package['dependencies'] : []),
            ...(is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : []),
        ];
        $name = is_string($composer['name'] ?? null) ? basename((string) $composer['name']) : basename($this->paths->basePath());

        return [
            'name' => $name !== '' ? $name : 'app',
            'root' => '.',
            'laravel' => $this->majorVersion($this->installedVersion('laravel/framework') ?? $this->app->version()),
            'inertia' => $this->detectInertiaVersion($dependencies, $nodeDependencies),
            'renderer' => $adapter,
            'typescript' => is_file($this->paths->absolute('tsconfig.json')) || array_key_exists('typescript', $nodeDependencies),
            'boostInstalled' => $this->detectBoost($dependencies),
            'iakVersion' => $this->packageVersion($composer),
        ];
    }

    /**
     * @param array<string, mixed> $project
     *
     * @return array<string, mixed>
     */
    private function projectPayload(array $project): array
    {
        return [
            'name' => $project['name'],
            'root' => '.',
            'laravel' => $project['laravel'],
            'inertia' => $project['inertia'],
            'renderer' => $project['renderer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boostPayload(bool $installed): array
    {
        return [
            'installed' => $installed,
            'status' => $installed ? 'available' : 'missing',
            'nextAction' => $installed ? null : 'composer require laravel/boost --dev && php artisan boost:install',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function artifactsPayload(): array
    {
        return [
            'runtimeConfig' => ['kind' => 'json', 'path' => '.iak/config.json'],
            'initState' => ['kind' => 'json', 'path' => '.iak/state/init.json'],
            'rules' => ['kind' => 'markdown', 'path' => '.iak/rules/inertia-agent-kit.md'],
            'schemas' => ['kind' => 'directory', 'path' => '.iak/schemas'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function nextActions(bool $boostInstalled): array
    {
        return [
            ...($boostInstalled ? [] : [[
                'type' => 'run',
                'summary' => 'Install Laravel Boost for Laravel-aware agent context.',
                'command' => 'composer require laravel/boost --dev && php artisan boost:install',
            ]]),
            [
                'type' => 'run',
                'summary' => 'Verify IAK conventions and generated artifacts.',
                'command' => 'php artisan iak:verify --json',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function commandsPayload(): array
    {
        return [
            'init' => 'php artisan iak:init --json',
            'makeResource' => 'php artisan iak:make-resource <resource> --json',
            'audit' => 'php artisan iak:audit --json',
            'feedbackList' => 'php artisan iak:feedback list --json',
            'feedbackShow' => 'php artisan iak:feedback show <id> --json',
            'feedbackResolve' => 'php artisan iak:feedback resolve <id> --evidence=.iak/runs/<run-id>/verify.json --json',
            'verify' => 'php artisan iak:verify --json',
        ];
    }

    /**
     * @return list<string>
     */
    private function boostUses(): array
    {
        return ['laravel_docs', 'app_info', 'absolute_urls', 'browser_logs', 'database_schema', 'log_entries'];
    }

    /**
     * @return list<string>
     */
    private function iakUses(): array
    {
        return ['manifest', 'tokens', 'components', 'audit', 'verify', 'feedback', 'handoff'];
    }

    /**
     * @param array<string, mixed> $project
     * @param array<string, mixed> $boost
     * @param list<array<string, mixed>> $files
     * @param list<array<string, mixed>> $errors
     *
     * @return array{exitCode: int, payload: array<string, mixed>}
     */
    private function failed(string $runId, string $createdAt, string $adapter, array $project, array $boost, array $files, array $errors, int $exitCode): array
    {
        return [
            'exitCode' => $exitCode,
            'payload' => [
                'schema' => self::INIT_SCHEMA,
                'event' => 'iak.init.failed.v1',
                'status' => 'failed',
                'summary' => 'IAK init failed.',
                'runId' => $runId,
                'project' => $this->projectPayload($project),
                'files' => $files,
                'manifest' => $this->manifestPayload('not_written'),
                'boost' => $boost,
                'artifacts' => $this->artifactsPayload(),
                'nextActions' => [],
                'errors' => $errors,
                'meta' => $this->meta($createdAt, $adapter),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function manifestPayload(string $status): array
    {
        return [
            'schema' => self::MANIFEST_SCHEMA,
            'path' => '.iak/manifest/iak.manifest.v1.json',
            'status' => $status,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function meta(string $createdAt, string $adapter): array
    {
        return [
            'createdAt' => $createdAt,
            'iakVersion' => $this->packageVersion($this->readJson('composer.json')),
            'adapter' => $this->adapterId($adapter),
        ];
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = function_exists('config') ? config("inertia-agent-kit.{$key}", $default) : $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function adapterId(string $adapter): string
    {
        return 'laravel-inertia-'.$adapter;
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function packageVersion(array $composer): string
    {
        return is_string($composer['version'] ?? null) && $composer['version'] !== ''
            ? $composer['version']
            : ($this->installedVersion('fbarrento/inertia-agent-kit') ?? '0.1.0');
    }

    private function installedVersion(string $package): ?string
    {
        if (! class_exists('Composer\\InstalledVersions') || ! \Composer\InstalledVersions::isInstalled($package)) {
            return null;
        }

        return \Composer\InstalledVersions::getPrettyVersion($package);
    }

    /**
     * @param array<string, mixed> $dependencies
     * @param array<string, mixed> $nodeDependencies
     */
    private function detectInertiaVersion(array $dependencies, array $nodeDependencies): string
    {
        foreach (['inertiajs/inertia-laravel', '@inertiajs/react', '@inertiajs/vue3', '@inertiajs/svelte'] as $package) {
            $version = $dependencies[$package] ?? $nodeDependencies[$package] ?? null;

            if (is_string($version)) {
                return $this->majorVersion($version);
            }
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $dependencies
     */
    private function detectBoost(array $dependencies): bool
    {
        return array_key_exists('laravel/boost', $dependencies)
            || $this->installedVersion('laravel/boost') !== null
            || class_exists('Laravel\\Boost\\BoostServiceProvider');
    }

    private function majorVersion(string $version): string
    {
        return preg_match('/(\d+)/', $version, $matches) === 1 ? $matches[1].'.x' : 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $absolute = $this->paths->absolute($path);

        if (! is_file($absolute)) {
            return [];
        }

        try {
            $decoded = json_decode(file_get_contents($absolute) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @throws JsonException
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    }

    private function runId(): string
    {
        try {
            return 'run_'.bin2hex(random_bytes(8));
        } catch (Throwable) {
            return 'run_'.substr(hash('sha256', uniqid('', true)), 0, 16);
        }
    }
}
