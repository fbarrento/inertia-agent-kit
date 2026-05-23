<?php

declare(strict_types=1);

use InertiaAgentKit\Actions\ResolveHandoffPath;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->resolveHandoffPath = new ResolveHandoffPath;
});

test('normalizes explicit project relative paths', function (): void {
    expect($this->resolveHandoffPath->handle(path: './.iak//runs/run_01j/handoff.json'))->toBe('.iak/runs/run_01j/handoff.json')
        ->and($this->resolveHandoffPath->handle(path: 'resources\\js\\pages\\vehicles\\index.tsx'))->toBe('resources/js/pages/vehicles/index.tsx');
});

test('resolves handoff run paths from direct defaults and configured runs path', function (): void {
    $configured = new ResolveHandoffPath(runsPath: 'storage/iak-runs/');
    $slashOnlyConfigured = new ResolveHandoffPath(runsPath: '///');
    $invalidConfigured = new ResolveHandoffPath(runsPath: ['not-a-path']);

    expect($this->resolveHandoffPath->handle(runId: 'run_default'))->toBe('.iak/runs/run_default/handoff.json')
        ->and($configured->handle(runId: 'run_01j'))->toBe('storage/iak-runs/run_01j/handoff.json')
        ->and($slashOnlyConfigured->handle(runId: 'run_slash_only'))->toBe('.iak/runs/run_slash_only/handoff.json')
        ->and($invalidConfigured->handle(runId: 'run_invalid_config'))->toBe('.iak/runs/run_invalid_config/handoff.json');
});

test('resolves handoff run paths from Laravel config when container built', function (): void {
    config()->set('inertia-agent-kit.paths.runs', 'storage/agent-runs');

    $resolver = $this->app->make(ResolveHandoffPath::class);

    expect($resolver->handle(runId: 'run_configured'))->toBe('storage/agent-runs/run_configured/handoff.json');
});

test('rejects invalid explicit paths and run ids', function (): void {
    expect($this->resolveHandoffPath->handle())->toBeNull()
        ->and($this->resolveHandoffPath->handle(runId: '  '))->toBeNull()
        ->and($this->resolveHandoffPath->handle(path: '/tmp/handoff.json'))->toBeNull()
        ->and($this->resolveHandoffPath->handle(path: '../handoff.json'))->toBeNull()
        ->and($this->resolveHandoffPath->handle(path: '.iak/../handoff.json'))->toBeNull()
        ->and($this->resolveHandoffPath->handle(path: '.git/config'))->toBeNull()
        ->and($this->resolveHandoffPath->handle(path: 'C:\\project\\handoff.json'))->toBeNull()
        ->and($this->resolveHandoffPath->handle(path: "valid\0path.json"))->toBeNull()
        ->and($this->resolveHandoffPath->handle(path: '.'))->toBeNull()
        ->and($this->resolveHandoffPath->handle(path: '  '))->toBeNull()
        ->and((new ResolveHandoffPath(runsPath: '  '))->handle(runId: 'run_empty'))->toBeNull()
        ->and($this->resolveHandoffPath->handle(runId: '../escape'))->toBeNull();
});
