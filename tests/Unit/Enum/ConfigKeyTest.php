<?php

declare(strict_types=1);

use InertiaAgentKit\Enum\ConfigKey;

test('maps stable package config keys to Laravel config paths', function (): void {
    expect(ConfigKey::RunsPath->value)->toBe('inertia-agent-kit.paths.runs');
});
