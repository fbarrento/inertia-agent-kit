<?php

declare(strict_types=1);

namespace InertiaAgentKit\Enum;

enum ArtifactKind: string
{
    case Json = 'json';
    case Screenshot = 'screenshot';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $kind): string => $kind->value,
            self::cases(),
        );
    }
}
