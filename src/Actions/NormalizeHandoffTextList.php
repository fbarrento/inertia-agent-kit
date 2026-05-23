<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

final readonly class NormalizeHandoffTextList
{
    /**
     * @return list<string>
     */
    public function handle(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $values = [];

        foreach ($value as $item) {
            if (! is_scalar($item) || is_bool($item)) {
                continue;
            }

            $item = trim((string) $item);

            if ($item !== '') {
                $values[] = $item;
            }
        }

        return $values;
    }
}
