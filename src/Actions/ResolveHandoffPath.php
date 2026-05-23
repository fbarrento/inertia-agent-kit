<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use Illuminate\Container\Attributes\Config;
use InertiaAgentKit\Enum\ConfigKey;
use InertiaAgentKit\Support\HandoffPathDefaults;

final readonly class ResolveHandoffPath
{
    public function __construct(
        #[Config(ConfigKey::RunsPath->value)]
        private mixed $runsPath = null,
    ) {}

    public function handle(?string $path = null, ?string $runId = null): ?string
    {
        if ($path !== null) {
            $candidatePath = $path;
        } elseif ($runId !== null && trim($runId) !== '') {
            $runsPath = is_string($this->runsPath) && $this->runsPath !== ''
                ? trim(str_replace('\\', '/', $this->runsPath), '/')
                : HandoffPathDefaults::RUNS;

            if ($runsPath === '') {
                $runsPath = HandoffPathDefaults::RUNS;
            }

            $candidatePath = $runsPath.'/'.trim($runId).'/handoff.json';
        } else {
            return null;
        }

        if (str_contains($candidatePath, "\0")) {
            return null;
        }

        $candidatePath = trim(str_replace('\\', '/', $candidatePath));

        if ($candidatePath === '' || str_starts_with($candidatePath, '/') || preg_match('/^[A-Za-z]:\\//', $candidatePath) === 1) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $candidatePath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' || $segment === '.git') {
                return null;
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return null;
        }

        return implode('/', $segments);
    }
}
