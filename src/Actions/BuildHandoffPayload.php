<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use InertiaAgentKit\Data\CreateHandoffData;
use InertiaAgentKit\Handoff\HandoffCreator;
use InertiaAgentKit\Support\ArrayData;

final readonly class BuildHandoffPayload implements BuildHandoffPayloadBuilder
{
    private HandoffCreator $handoffCreator;

    public function __construct(?HandoffCreator $handoffCreator = null)
    {
        $this->handoffCreator = $handoffCreator ?? new HandoffCreator;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  CreateHandoffData|array<string, mixed>  $createHandoffData
     * @return array<string, mixed>
     * @return array<string, mixed>
     */
    public function handle(CreateHandoffData|array $createHandoffData, array $config = []): array
    {
        if (is_array($createHandoffData)) {
            return $this->handoffCreator->create(ArrayData::stringMap($createHandoffData), $config);
        }

        return $this->handoffCreator->create($createHandoffData->jsonSerialize(), $config);
    }
}
