<?php

declare(strict_types=1);

namespace InertiaAgentKit\Console;

use Illuminate\Console\Command;
use InertiaAgentKit\Actions\HandleHandoffCommand;
use InertiaAgentKit\Data\HandoffCommandInputData;
use InertiaAgentKit\Support\ArrayData;

final class HandoffCommand extends Command
{
    public function __construct(private readonly HandleHandoffCommand $handleHandoffCommand)
    {
        parent::__construct();
    }

    protected $signature = 'iak:handoff
        {action=create : Handoff action: create or validate}
        {path? : Optional handoff artifact path for validate}
        {--run-id= : Optional handoff run id}
        {--task= : Task description for the handoff}
        {--summary= : Summary of the completed work}
        {--status=completed : Requested handoff status}
        {--changed-file=* : Changed file entry as role:action:path}
        {--changed-files= : Optional JSON artifact with grouped changedFiles}
        {--verify= : Optional iak.verify.v1 artifact path}
        {--audit= : Optional iak.audit.v1 artifact path}
        {--tests= : Optional tests artifact path}
        {--feedback-unresolved= : Optional unresolved feedback count}
        {--note=* : Handoff note}
        {--next-action=* : Follow-up action}
        {--json : Emit one machine-readable JSON response}
        {--pretty : Pretty-print JSON when JSON output is active}';

    protected $description = 'Create or validate an Inertia Agent Kit handoff artifact.';

    public function handle(): int
    {
        $rawAction = $this->argument('action');
        $action = strtolower(trim(is_string($rawAction) ? $rawAction : 'create'));
        if ($action === '') {
            $action = 'create';
        }

        $payload = [];
        if ($action === 'create') {
            $runId = $this->option('run-id');
            $runId = is_scalar($runId) ? trim((string) $runId) : '';
            $runId = $runId === '' ? null : $runId;

            $task = $this->option('task');
            $task = is_scalar($task) ? trim((string) $task) : '';
            $task = $task === '' ? null : $task;

            $summary = $this->option('summary');
            $summary = is_scalar($summary) ? trim((string) $summary) : '';
            $summary = $summary === '' ? null : $summary;

            $status = $this->option('status');
            $status = is_scalar($status) ? trim((string) $status) : '';
            $status = $status === '' ? 'completed' : $status;

            $audit = $this->option('audit');
            $audit = is_scalar($audit) ? trim((string) $audit) : '';
            $audit = $audit === '' ? null : $audit;

            $tests = $this->option('tests');
            $tests = is_scalar($tests) ? trim((string) $tests) : '';
            $tests = $tests === '' ? null : $tests;

            $verify = $this->option('verify');
            $verify = is_scalar($verify) ? trim((string) $verify) : '';
            $verify = $verify === '' ? null : $verify;

            $feedbackUnresolved = $this->option('feedback-unresolved');
            $feedbackUnresolved = is_scalar($feedbackUnresolved) ? trim((string) $feedbackUnresolved) : '';
            $feedbackUnresolved = $feedbackUnresolved === '' ? null : $feedbackUnresolved;

            $changedFile = [];
            if (is_array($this->option('changed-file'))) {
                foreach ($this->option('changed-file') as $item) {
                    if (is_scalar($item)) {
                        $normalizedItem = trim((string) $item);
                        if ($normalizedItem !== '') {
                            $changedFile[] = $normalizedItem;
                        }
                    }
                }
            }

            $notes = [];
            if (is_array($this->option('note'))) {
                foreach ($this->option('note') as $item) {
                    if (is_scalar($item)) {
                        $normalizedItem = trim((string) $item);
                        if ($normalizedItem !== '') {
                            $notes[] = $normalizedItem;
                        }
                    }
                }
            }

            $nextAction = [];
            if (is_array($this->option('next-action'))) {
                foreach ($this->option('next-action') as $item) {
                    if (is_scalar($item)) {
                        $normalizedItem = trim((string) $item);
                        if ($normalizedItem !== '') {
                            $nextAction[] = $normalizedItem;
                        }
                    }
                }
            }

            $changedFilesPath = $this->option('changed-files');
            $changedFilesPath = is_scalar($changedFilesPath) ? trim((string) $changedFilesPath) : '';
            $changedFilesPath = $changedFilesPath === '' ? null : $changedFilesPath;

            $payload = [
                'command' => is_string($this->getName()) ? $this->getName() : 'iak:handoff',
                'runId' => $runId,
                'task' => $task,
                'summary' => $summary,
                'status' => $status,
                'changedFile' => $changedFile,
                'changedFilesPath' => $changedFilesPath,
                'audit' => $audit,
                'tests' => $tests,
                'verify' => $verify,
                'feedbackUnresolved' => $feedbackUnresolved,
                'note' => $notes,
                'nextAction' => $nextAction,
            ];
        }

        $path = $this->argument('path');
        if (! is_scalar($path) || is_bool($path)) {
            $path = null;
        } else {
            $path = trim((string) $path);
            $path = $path === '' ? null : $path;
        }

        $runId = $this->option('run-id');
        if (! is_scalar($runId) || is_bool($runId)) {
            $runId = null;
        } else {
            $runId = trim((string) $runId);
            $runId = $runId === '' ? null : $runId;
        }

        $result = $this->handleHandoffCommand->handle(
            new HandoffCommandInputData(
                action: $action,
                payload: $payload,
                path: $path,
                runId: $runId,
            ),
            ArrayData::stringMap(config('inertia-agent-kit')),
        );

        $shouldEmitJson = getenv('IAK_AGENT') === '1' || (bool) $this->option('json');

        if (! $shouldEmitJson) {
            $this->line($result->payload->summary !== '' ? $result->payload->summary : 'Handoff command finished.');

            return $result->status;
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ((bool) $this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $encoded = json_encode($result->payload->jsonSerialize(), $flags);
        if ($encoded === false) {
            return 4;
        }

        $this->output->writeln($encoded);

        return $result->status;
    }
}
