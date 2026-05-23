<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\ValidateHandoffPayload;
use InertiaAgentKit\Data\HandoffValidationData;
use Random\RandomException;
use Tests\Utils\HandoffPayloadFixture;

beforeEach(function (): void {
    $this->validateHandoffPayload = new ValidateHandoffPayload;
});

test('validates handoff payloads through validation data',
    /**
     * @throws RandomException
     */
    function (): void {
        $basePath = HandoffPayloadFixture::makeBasePath();

        try {
            $result = $this->validateHandoffPayload->handle(
                HandoffPayloadFixture::validPayload($basePath),
                $basePath,
            );
            $serialized = $result->jsonSerialize();

            expect($result)->toBeInstanceOf(HandoffValidationData::class)
                ->and($serialized['valid'])->toBeTrue()
                ->and($serialized['status'])->toBe('valid')
                ->and($serialized['errors'])->toBe([])
                ->and($serialized['nextActions'])->toBe([]);
        } finally {
            HandoffPayloadFixture::removeDirectory($basePath);
        }
    });

test('serializes validation errors and next actions through validation data',
    /**
     * @throws RandomException
     */
    function (): void {
        $basePath = HandoffPayloadFixture::makeBasePath();

        try {
            $payload = HandoffPayloadFixture::validPayload($basePath);
            $payload['nextActions'] = [[
                'type' => 'fix',
                'summary' => 'Run another implementation pass.',
                'command' => 'composer test',
                'blocking' => true,
            ]];

            $serialized = $this->validateHandoffPayload
                ->handle($payload, $basePath)
                ->jsonSerialize();

            expect($serialized['valid'])->toBeFalse()
                ->and($serialized['status'])->toBe('invalid')
                ->and($serialized['errors'][0]['code'])->toBe('handoff.next_actions.blocking')
                ->and($serialized['nextActions'][0])->toBe([
                    'type' => 'fix',
                    'summary' => 'Run another implementation pass.',
                    'command' => 'composer test',
                    'blocking' => true,
                ]);
        } finally {
            HandoffPayloadFixture::removeDirectory($basePath);
        }
    });
