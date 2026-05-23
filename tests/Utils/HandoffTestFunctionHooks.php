<?php

declare(strict_types=1);

namespace InertiaAgentKit\Handoff;

if (! function_exists(__NAMESPACE__.'\\random_bytes')) {
    function random_bytes(int $length): string
    {
        if (getenv('I_AK_FORCE_RANDOM_BYTES_THROW') === '1') {
            throw new \RuntimeException('Forced random_bytes failure for handoff tests.');
        }

        return \random_bytes($length);
    }
}

if (! function_exists(__NAMESPACE__.'\\function_exists')) {
    function function_exists(string $function): bool
    {
        if ($function === 'mb_strlen' && getenv('I_AK_FORCE_MB_STRLEN_FALLBACK') === '1') {
            return false;
        }

        return \function_exists($function);
    }
}
