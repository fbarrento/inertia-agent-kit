<?php

declare(strict_types=1);

namespace InertiaAgentKit\Data;

use InertiaAgentKit\Enum\HandoffStatus;
use JsonSerializable;

final readonly class HandoffValidationData implements JsonSerializable
{
    /**
     * @param  list<HandoffErrorData>  $errors
     * @param  list<NextActionData>  $nextActions
     */
    public function __construct(
        public bool $valid,
        public HandoffStatus $status,
        public array $errors = [],
        public array $nextActions = [],
    ) {}

    /**
     * @return array{
     *     valid: bool,
     *     status: string,
     *     errors: list<array{code: string, message: string, file: string|null, line: int|null, details: array<string, mixed>}>,
     *     nextActions: list<array{type: string, summary: string, command?: string, blocking?: bool}>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'valid' => $this->valid,
            'status' => $this->status->value,
            'errors' => $this->serializeErrors(),
            'nextActions' => $this->serializeNextActions(),
        ];
    }

    /**
     * @return list<array{code: string, message: string, file: string|null, line: int|null, details: array<string, mixed>}>
     */
    private function serializeErrors(): array
    {
        $errors = [];

        foreach ($this->errors as $error) {
            $errors[] = $error->jsonSerialize();
        }

        return $errors;
    }

    /**
     * @return list<array{type: string, summary: string, command?: string, blocking?: bool}>
     */
    private function serializeNextActions(): array
    {
        $nextActions = [];

        foreach ($this->nextActions as $nextAction) {
            $nextActions[] = $nextAction->jsonSerialize();
        }

        return $nextActions;
    }
}
