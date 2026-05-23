<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use InertiaAgentKit\Console\Concerns\EmitsJson;
use InertiaAgentKit\Init\Initializer;
use InertiaAgentKit\Support\ArrayData;
use JsonException;

final class InitCommand extends Command
{
    use EmitsJson;

    protected $signature = 'iak:init
        {--json : Emit a machine-readable JSON response}
        {--pretty : Pretty-print the JSON response}
        {--force : Refresh generated runtime artifacts}
        {--adapter=react : Renderer adapter to initialize}';

    protected $description = 'Initialize Inertia Agent Kit in a Laravel Inertia application.';

    /**
     * @throws JsonException
     */
    public function handle(): int
    {
        $result = (new Initializer($this->laravel))->run(
            $this->nullableOption('adapter') ?? 'react',
            (bool) $this->option('force'),
        );

        if ($this->shouldEmitJson()) {
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

            if ((bool) $this->option('pretty')) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $this->output->writeln(json_encode($result['payload'], $flags));

            return $result['exitCode'];
        }

        $payload = $result['payload'];
        $this->line(ArrayData::stringAt($payload, ['summary'], 'IAK init completed.'));

        return $result['exitCode'];
    }

    private function nullableOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
