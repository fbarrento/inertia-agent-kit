<?php

declare(strict_types=1);

namespace Tests\Utils;

use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

final class ThrowingOutput extends BufferedOutput
{
    private int $writelnCallCount = 0;

    public function writeln(mixed $messages, int $options = self::OUTPUT_NORMAL): void
    {
        if ($this->writelnCallCount === 0) {
            $this->writelnCallCount++;

            throw new RuntimeException('Unable to emit command output.');
        }

        $this->writelnCallCount++;

        parent::writeln($messages, $options);
    }
}
