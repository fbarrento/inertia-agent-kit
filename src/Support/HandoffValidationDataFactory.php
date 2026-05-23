<?php

declare(strict_types=1);

namespace InertiaAgentKit\Support;

use InertiaAgentKit\Data\HandoffErrorData;
use InertiaAgentKit\Data\HandoffValidationData;
use InertiaAgentKit\Data\NextActionData;
use InertiaAgentKit\Enum\HandoffStatus;

final readonly class HandoffValidationDataFactory
{
    /**
     * @param  array{valid: bool, status: 'valid'|'invalid', errors: list<array<string, mixed>>, nextActions: list<array<string, mixed>>}  $result
     */
    public function make(array $result): HandoffValidationData
    {
        return new HandoffValidationData(
            valid: $result['valid'],
            status: HandoffStatus::from($result['status']),
            errors: $this->errors($result['errors']),
            nextActions: $this->nextActions($result['nextActions']),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     * @return list<HandoffErrorData>
     */
    private function errors(array $errors): array
    {
        $items = [];

        foreach ($errors as $error) {
            $line = ArrayData::valueAt($error, ['line']);

            $items[] = new HandoffErrorData(
                code: $this->stringValue(ArrayData::valueAt($error, ['code'])),
                message: $this->stringValue(ArrayData::valueAt($error, ['message'])),
                file: $this->nullableStringValue(ArrayData::valueAt($error, ['file'])),
                line: is_int($line) ? $line : null,
                details: ArrayData::stringMap(ArrayData::valueAt($error, ['details'])),
            );
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $nextActions
     * @return list<NextActionData>
     */
    private function nextActions(array $nextActions): array
    {
        $items = [];

        foreach ($nextActions as $nextAction) {
            $blocking = ArrayData::valueAt($nextAction, ['blocking']);

            $items[] = new NextActionData(
                type: $this->stringValue(ArrayData::valueAt($nextAction, ['type'])),
                summary: $this->stringValue(ArrayData::valueAt($nextAction, ['summary'])),
                command: $this->nullableStringValue(ArrayData::valueAt($nextAction, ['command'])),
                blocking: is_bool($blocking) ? $blocking : null,
            );
        }

        return $items;
    }

    private function stringValue(mixed $value): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return '';
        }

        return (string) $value;
    }

    private function nullableStringValue(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        return (string) $value;
    }
}
