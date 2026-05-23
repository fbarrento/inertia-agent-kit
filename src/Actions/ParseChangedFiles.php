<?php

declare(strict_types=1);

namespace InertiaAgentKit\Actions;

use InertiaAgentKit\Handoff\ChangedFileParser;

final readonly class ParseChangedFiles
{
    private ChangedFileParser $changedFileParser;

    public function __construct(?ChangedFileParser $changedFileParser = null)
    {
        $this->changedFileParser = $changedFileParser ?? new ChangedFileParser;
    }

    /**
     * @param  list<string>  $entries
     * @return array{changedFiles: array<string, list<array{path: string, action: string}>>, errors: list<array<string, mixed>>}
     */
    public function handle(array $entries): array
    {
        return $this->changedFileParser->parse($entries);
    }
}
