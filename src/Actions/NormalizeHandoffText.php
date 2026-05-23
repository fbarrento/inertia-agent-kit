<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

final readonly class NormalizeHandoffText
{
    public function handle(mixed $value, ?string $fallback = null): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return $fallback;
        }

        $value = trim((string) $value);

        return $value === '' ? $fallback : $value;
    }
}
