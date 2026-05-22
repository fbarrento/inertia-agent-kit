<?php

declare(strict_types=1);

namespace InertiaAgentKit\Feedback;

use RuntimeException;

final class FeedbackException extends RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $exitCode = 2,
        private readonly ?string $errorFile = null,
        private readonly array $details = [],
    ) {
        parent::__construct($message, $exitCode);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function filePath(): ?string
    {
        return $this->errorFile;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
