<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use InertiaAgentKit\Console\Concerns\EmitsJson;
use InertiaAgentKit\Scaffolding\ResourceScaffoldOptions;
use InertiaAgentKit\Scaffolding\ResourceScaffolder;

final class MakeResourceCommand extends Command
{
    use EmitsJson;

    protected $signature = 'iak:make-resource
        {resource? : Plural kebab-case resource route name}
        {--adapter=react : Renderer adapter}
        {--controller= : Fully qualified Laravel controller class}
        {--route-name= : Laravel resource route name}
        {--singular= : Singular kebab-case resource name when inference is ambiguous}
        {--only= : Comma-separated page actions: index,show,create,edit}
        {--except= : Comma-separated page actions to omit}
        {--dry-run : Return the file plan without writing}
        {--force : Overwrite scaffold-owned generated files}
        {--allow-missing-generated-types : Write expected generated type imports without checking generated contracts}
        {--json : Emit a machine-readable JSON response}';

    protected $description = 'Scaffold an Inertia Agent Kit resource surface.';

    public function handle(): int
    {
        $resource = (string) ($this->argument('resource') ?? '');
        $command = 'php artisan '.($this->getName() ?? 'iak:make-resource').($resource !== '' ? " {$resource}" : '');

        $plan = (new ResourceScaffolder($this->laravel))->scaffold(new ResourceScaffoldOptions(
            resource: $resource,
            adapter: (string) $this->option('adapter'),
            dryRun: (bool) $this->option('dry-run'),
            force: (bool) $this->option('force'),
            singular: $this->nullableOption('singular'),
            only: $this->nullableOption('only'),
            except: $this->nullableOption('except'),
            allowMissingGeneratedTypes: (bool) $this->option('allow-missing-generated-types'),
            controller: $this->nullableOption('controller'),
            routeName: $this->nullableOption('route-name'),
            command: $command,
        ));

        $status = $plan['status'] === 'failed' ? self::INVALID : self::SUCCESS;

        if ($this->shouldEmitJson()) {
            return $this->emitJson($plan, $status);
        }

        if ($plan['status'] === 'failed') {
            $firstError = $plan['errors'][0]['message'] ?? 'Resource scaffold failed.';

            $this->error((string) $firstError);

            return $status;
        }

        $verb = $plan['mode'] === 'dry-run' ? 'Planned' : 'Scaffolded';

        $this->line("{$verb} {$plan['resource']['name']} resource.");

        return $status;
    }

    private function nullableOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
