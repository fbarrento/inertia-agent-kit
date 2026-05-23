<?php

declare(strict_types=1);

use InertiaAgentKit\Enum\HandoffStatus;

test('exposes handoff status backed values', function (): void {
    expect(HandoffStatus::values())->toBe([
        'completed',
        'blocked',
        'valid',
        'invalid',
    ]);
});

test('exposes concrete handoff statuses', function (): void {
    expect(HandoffStatus::Completed->value)->toBe('completed')
        ->and(HandoffStatus::Blocked->value)->toBe('blocked')
        ->and(HandoffStatus::Valid->value)->toBe('valid')
        ->and(HandoffStatus::Invalid->value)->toBe('invalid');
});
