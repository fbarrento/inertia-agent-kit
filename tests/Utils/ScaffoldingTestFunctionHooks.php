<?php

declare(strict_types=1);

namespace InertiaAgentKit\Scaffolding;

if (! function_exists(__NAMESPACE__.'\\file_get_contents')) {
    function file_get_contents(string $filename, mixed ...$arguments): string|false
    {
        if (
            getenv('I_AK_FORCE_STUB_RENDERER_READ_FAIL') === '1'
            && str_ends_with($filename, 'blocked.stub')
        ) {
            return false;
        }

        return \file_get_contents($filename, ...$arguments);
    }
}
