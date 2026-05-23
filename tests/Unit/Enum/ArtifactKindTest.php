<?php

declare(strict_types=1);

use InertiaAgentKit\Enum\ArtifactKind;

test('exposes artifact kind backed values', function (): void {
    expect(ArtifactKind::values())->toBe([
        'json',
        'screenshot',
    ]);
});

test('exposes concrete artifact kinds', function (): void {
    expect(ArtifactKind::Json->value)->toBe('json')
        ->and(ArtifactKind::Screenshot->value)->toBe('screenshot');
});
