<?php

declare(strict_types=1);

use InertiaAgentKit\Data\NextActionData;

test('serializes next actions with current handoff metadata fields', function (): void {
    $action = new NextActionData(
        type: 'fix',
        summary: 'Run browser verification and attach a screenshot artifact.',
        command: 'php artisan iak:verify --json',
        blocking: true,
    );

    expect($action->jsonSerialize())->toBe([
        'type' => 'fix',
        'summary' => 'Run browser verification and attach a screenshot artifact.',
        'command' => 'php artisan iak:verify --json',
        'blocking' => true,
    ]);
});

test('omits absent optional next action fields', function (): void {
    $action = new NextActionData(
        type: 'review',
        summary: 'Review the handoff.',
    );

    expect($action->jsonSerialize())->toBe([
        'type' => 'review',
        'summary' => 'Review the handoff.',
    ]);
});
