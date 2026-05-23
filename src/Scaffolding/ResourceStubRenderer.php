<?php

declare(strict_types=1);

namespace InertiaAgentKit\Scaffolding;

use RuntimeException;

final readonly class ResourceStubRenderer
{
    public function __construct(
        private string $stubRoot = __DIR__.'/../../resources/stubs'
    ) {}

    /**
     * @param  array<string, string>  $context
     */
    public function render(string $adapter, string $stub, array $context): string
    {
        $path = $this->stubRoot.'/'.$adapter.'/'.$stub;

        if (! is_file($path)) {
            throw new RuntimeException("Missing resource scaffold stub [{$adapter}/{$stub}].");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read resource scaffold stub [{$adapter}/{$stub}].");
        }

        foreach ($context as $key => $value) {
            $contents = str_replace('{{ '.$key.' }}', $value, $contents);
        }

        return rtrim($contents).PHP_EOL;
    }
}
