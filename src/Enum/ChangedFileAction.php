<?php

declare(strict_types=1);

namespace InertiaAgentKit\Enum;

enum ChangedFileAction: string
{
    case Create = 'create';
    case Modify = 'modify';
    case Delete = 'delete';
    case Rename = 'rename';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $action): string => $action->value,
            self::cases(),
        );
    }
}
