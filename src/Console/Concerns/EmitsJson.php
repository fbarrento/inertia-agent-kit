<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console\Concerns;

use Illuminate\Console\Command;

trait EmitsJson
{
    /**
     * @param array<string, mixed> $payload
     */
    protected function respond(array $payload, ?string $line = null, int $status = Command::SUCCESS): int
    {
        if ($this->shouldEmitJson()) {
            return $this->emitJson($payload, $status);
        }

        if ($line !== null) {
            $this->line($line);
        }

        return $status;
    }

    protected function shouldEmitJson(): bool
    {
        if (getenv('IAK_AGENT') === '1') {
            return true;
        }

        return $this->commandDefinesOption('json') && (bool) $this->option('json');
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitJson(array $payload, int $status = Command::SUCCESS): int
    {
        $this->output->writeln(json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));

        return $status;
    }

    private function commandDefinesOption(string $name): bool
    {
        foreach ($this->getDefinition()->getOptions() as $option) {
            if ($option->getName() === $name) {
                return true;
            }
        }

        return false;
    }
}
