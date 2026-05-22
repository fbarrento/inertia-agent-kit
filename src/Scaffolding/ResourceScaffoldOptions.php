<?php

declare(strict_types=1);

namespace InertiaAgentKit\Scaffolding;

final class ResourceScaffoldOptions
{
    public function __construct(
        public readonly string $resource,
        public readonly string $adapter,
        public readonly bool $dryRun,
        public readonly bool $force,
        public readonly ?string $singular,
        public readonly ?string $only,
        public readonly ?string $except,
        public readonly bool $allowMissingGeneratedTypes,
        public readonly ?string $controller,
        public readonly ?string $routeName,
        public readonly string $command,
    ) {
    }
}
