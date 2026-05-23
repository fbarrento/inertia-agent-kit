<?php

declare(strict_types=1);

namespace InertiaAgentKit\Enum;

enum EvidenceStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Pending = 'pending';

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
