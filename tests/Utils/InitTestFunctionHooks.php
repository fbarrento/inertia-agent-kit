<?php

declare(strict_types=1);

namespace InertiaAgentKit\Init;

if (! function_exists(__NAMESPACE__.'\\random_bytes')) {
    function random_bytes(int $length): string
    {
        if (getenv('I_AK_FORCE_INIT_RANDOM_BYTES_THROW') === '1') {
            throw new \RuntimeException('Forced random_bytes failure for init tests.');
        }

        return \random_bytes($length);
    }
}
