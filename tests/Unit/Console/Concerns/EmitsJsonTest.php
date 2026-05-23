<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use InertiaAgentKit\Console\Concerns\EmitsJson;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

final class EmitsJsonCommandStub extends Command
{
    use EmitsJson;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private array $payload = [], private ?string $line = null, private int $status = self::SUCCESS, bool $defineJsonOption = false)
    {
        parent::__construct('iak:test-emit-json');

        if ($defineJsonOption) {
            $this->getDefinition()->addOption(new InputOption('json', null, InputOption::VALUE_NONE));
        }
    }

    public static function runWith(bool $defineJsonOption, array $input, array $payload, ?string $line = null, int $status = self::SUCCESS): array
    {
        $command = new self($payload, $line, $status, $defineJsonOption);

        $input = new ArrayInput($input, $command->getDefinition());
        $output = new BufferedOutput;

        $command->setLaravel(app());
        $result = $command->run($input, $output);

        return [$result, trim($output->fetch())];
    }

    public function handle(): int
    {
        return $this->respond($this->payload, $this->line, $this->status);
    }
}

test('emits json when agent mode is enabled via environment variable', function (): void {
    putenv('IAK_AGENT=1');

    [$status, $output] = EmitsJsonCommandStub::runWith(
        defineJsonOption: false,
        input: [],
        payload: ['status' => 'agent'],
    );

    expect($status)->toBe(0)
        ->and(json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR))->toBe(['status' => 'agent']);
});

test('falls back to line output when json output is disabled', function (): void {
    putenv('IAK_AGENT=');

    [$status, $output] = EmitsJsonCommandStub::runWith(
        defineJsonOption: false,
        input: [],
        payload: ['status' => 'line'],
        line: 'summary-line',
    );

    expect($status)->toBe(0)
        ->and($output)->toContain('summary-line');
});

test('emits json when command json option is defined and enabled', function (): void {
    putenv('IAK_AGENT=');

    [$status, $output] = EmitsJsonCommandStub::runWith(
        defineJsonOption: true,
        input: ['--json' => true],
        payload: ['status' => 'json-flag'],
    );

    expect($status)->toBe(0)
        ->and(json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR))->toBe(['status' => 'json-flag']);
});
