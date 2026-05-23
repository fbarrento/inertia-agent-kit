<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use InertiaAgentKit\InertiaAgentKitServiceProvider;

test('boots and merges package config defaults', function (): void {
    expect(config('inertia-agent-kit.adapter'))->toBe('react')
        ->and(config('inertia-agent-kit.paths.root'))->toBe('resources/js')
        ->and(config('inertia-agent-kit.generated.type_alias'))->toBe('@/types/generated')
        ->and(config('inertia-agent-kit.json_schemas.audit'))->toBe('iak.audit.v1')
        ->and(config('inertia-agent-kit.json_schemas.handoff'))->toBe('iak.handoff.v1')
        ->and(config('inertia-agent-kit.feedback.statuses'))->toBe([
            'pending',
            'in_progress',
            'resolved',
            'wont_fix',
            'duplicate',
        ]);
});

test('loads the service provider through testbench', function (): void {
    expect($this->app->getProvider(InertiaAgentKitServiceProvider::class))
        ->toBeInstanceOf(InertiaAgentKitServiceProvider::class);
});

test('declares laravel package auto discovery metadata', function (): void {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 2).'/composer.json') ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($composer['extra']['laravel']['providers'])
        ->toContain(InertiaAgentKitServiceProvider::class);
});

test('publishes the package config file', function (): void {
    $source = realpath(dirname(__DIR__, 2).'/config/inertia-agent-kit.php');

    expect(InertiaAgentKitServiceProvider::pathsToPublish(InertiaAgentKitServiceProvider::class, 'inertia-agent-kit-config'))
        ->toHaveKey($source);
});

test('registers the intended artisan command names', function (): void {
    expect(array_keys(Artisan::all()))->toContain(
        'iak:init',
        'iak:make-resource',
        'iak:audit',
        'iak:feedback',
        'iak:handoff',
        'iak:verify',
    );
});

test('registers json-capable command definitions', function (string $command): void {
    $registered = Artisan::all();

    expect($registered)->toHaveKey($command)
        ->and($registered[$command]->getDefinition()->hasOption('json'))->toBeTrue();
})->with([
    'init' => 'iak:init',
    'make-resource' => 'iak:make-resource',
    'audit' => 'iak:audit',
    'feedback' => 'iak:feedback',
    'handoff' => 'iak:handoff',
    'verify' => 'iak:verify',
]);

test('resolves provider config path through private path helper', function (): void {
    $provider = $this->app->getProvider(InertiaAgentKitServiceProvider::class);
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('configPath');

    expect($method->invoke($provider))->toBe(realpath(dirname(__DIR__, 2).'/config/inertia-agent-kit.php'));
});

test('skips boot logic when not running in console', function (): void {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->andReturnFalse();

    $provider = new InertiaAgentKitServiceProvider($app);

    expect(fn () => $provider->boot())->not->toThrow(Throwable::class);
});
