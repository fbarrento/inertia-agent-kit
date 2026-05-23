<?php

declare(strict_types=1);

use InertiaAgentKit\Support\HandoffPathDefaults;

test('defines configurable handoff path defaults outside actions and enums', function (): void {
    expect(HandoffPathDefaults::RUNS)->toBe('.iak/runs');
});
