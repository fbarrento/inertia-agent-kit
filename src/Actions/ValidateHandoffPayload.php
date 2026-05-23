<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use InertiaAgentKit\Data\HandoffValidationData;
use InertiaAgentKit\Handoff\HandoffValidator;
use InertiaAgentKit\Support\HandoffValidationDataFactory;

final readonly class ValidateHandoffPayload
{
    private HandoffValidator $handoffValidator;

    private HandoffValidationDataFactory $handoffValidationDataFactory;

    public function __construct(
        ?HandoffValidator $handoffValidator = null,
        ?HandoffValidationDataFactory $handoffValidationDataFactory = null,
    ) {
        $this->handoffValidator = $handoffValidator ?? new HandoffValidator;
        $this->handoffValidationDataFactory = $handoffValidationDataFactory ?? new HandoffValidationDataFactory;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, string $basePath): HandoffValidationData
    {
        return $this->handoffValidationDataFactory->make(
            $this->handoffValidator->validate($payload, $basePath),
        );
    }
}
