<?php

declare(strict_types=1);

namespace InertiaAgentKit\Enum;

enum HandoffStatus: string
{
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Valid = 'valid';
    case Invalid = 'invalid';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
