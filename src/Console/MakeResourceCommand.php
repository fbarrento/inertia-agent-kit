<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use InertiaAgentKit\Console\Concerns\EmitsJson;
use InertiaAgentKit\Scaffolding\ResourceScaffolder;
use InertiaAgentKit\Scaffolding\ResourceScaffoldOptions;
use InertiaAgentKit\Support\ArrayData;

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
        $resource = $this->nullableArgument('resource') ?? '';
        $command = 'php artisan '.($this->getName() ?? 'iak:make-resource').($resource !== '' ? " {$resource}" : '');

        $plan = (new ResourceScaffolder($this->laravel))->scaffold(new ResourceScaffoldOptions(
            resource: $resource,
            adapter: $this->nullableOption('adapter') ?? 'react',
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
            $errors = is_array($plan['errors'] ?? null) ? array_values($plan['errors']) : [];
            $firstError = ArrayData::stringAt(ArrayData::stringMap($errors[0] ?? null), ['message'], 'Resource scaffold failed.');

            $this->error($firstError);

            return $status;
        }

        $verb = $plan['mode'] === 'dry-run' ? 'Planned' : 'Scaffolded';

        $this->line("{$verb} ".ArrayData::stringAt($plan, ['resource', 'name'], $resource).' resource.');

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

    private function nullableArgument(string $name): ?string
    {
        $value = $this->argument($name);

        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
