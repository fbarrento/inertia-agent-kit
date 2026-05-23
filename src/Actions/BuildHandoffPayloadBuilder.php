<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use InertiaAgentKit\Data\CreateHandoffData;

interface BuildHandoffPayloadBuilder
{
    /**
     * @param  array<string, mixed>|CreateHandoffData  $createHandoffData
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function handle(CreateHandoffData|array $createHandoffData, array $config = []): array;
}
