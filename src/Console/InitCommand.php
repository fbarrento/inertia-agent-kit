<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use InertiaAgentKit\Console\Concerns\EmitsJson;
use InertiaAgentKit\Init\Initializer;
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
            (string) $this->option('adapter'),
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

        $this->line((string) ($result['payload']['summary'] ?? 'IAK init completed.'));

        return $result['exitCode'];
    }
}
