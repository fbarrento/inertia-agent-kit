<?php

declare(strict_types=1);

use InertiaAgentKit\Feedback\FeedbackException;

test('exposes structured feedback error details', function (): void {
    $exception = new FeedbackException(
        'feedback.test_error',
        'Feedback processing failed.',
        4,
        '.iak/feedback/fbk_test/record.json',
        ['status' => 'failed'],
    );

    expect($exception->errorCode())->toBe('feedback.test_error')
        ->and($exception->exitCode())->toBe(4)
        ->and($exception->filePath())->toBe('.iak/feedback/fbk_test/record.json')
        ->and($exception->details())->toMatchArray(['status' => 'failed'])
        ->and($exception->getMessage())->toBe('Feedback processing failed.');
});

test('uses defaults when optional feedback error metadata is omitted', function (): void {
    $exception = new FeedbackException(
        'feedback.minimal_error',
        'Minimal feedback error.',
    );

    expect($exception->errorCode())->toBe('feedback.minimal_error')
        ->and($exception->exitCode())->toBe(2)
        ->and($exception->filePath())->toBeNull()
        ->and($exception->details())->toBe([]);
});
