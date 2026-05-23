<?php

declare(strict_types=1);

use InertiaAgentKit\Enum\EvidenceStatus;

test('exposes evidence status backed values', function (): void {
    expect(EvidenceStatus::values())->toBe([
        'passed',
        'failed',
        'pending',
    ]);
});

test('exposes concrete evidence statuses', function (): void {
    expect(EvidenceStatus::Passed->value)->toBe('passed')
        ->and(EvidenceStatus::Failed->value)->toBe('failed')
        ->and(EvidenceStatus::Pending->value)->toBe('pending');
});
