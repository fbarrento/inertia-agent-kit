<?php

declare(strict_types=1);

namespace InertiaAgentKit\Enum;

enum ChangedFileRole: string
{
    case Page = 'page';
    case Feature = 'feature';
    case Story = 'story';
    case ComponentUi = 'component-ui';
    case ComponentApp = 'component-app';
    case Layout = 'layout';
    case Type = 'type';
    case Config = 'config';
    case Test = 'test';
    case Docs = 'docs';
    case Boost = 'boost';
    case Package = 'package';
    case Resource = 'resource';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
