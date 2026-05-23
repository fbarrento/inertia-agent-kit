<?php

declare(strict_types=1);

namespace InertiaAgentKit\Scaffolding;

final readonly class ResourceScaffoldOptions
{
    public function __construct(
        public string $resource,
        public string $adapter,
        public bool $dryRun,
        public bool $force,
        public ?string $singular,
        public ?string $only,
        public ?string $except,
        public bool $allowMissingGeneratedTypes,
        public ?string $controller,
        public ?string $routeName,
        public string $command,
    ) {}
}
