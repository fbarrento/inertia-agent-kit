<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use InertiaAgentKit\InertiaAgentKitServiceProvider;

it('boots and merges package config defaults', function (): void {
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

it('loads the service provider through testbench', function (): void {
    expect($this->app->getProvider(InertiaAgentKitServiceProvider::class))
        ->toBeInstanceOf(InertiaAgentKitServiceProvider::class);
});

it('declares laravel package auto discovery metadata', function (): void {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 2).'/composer.json') ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    expect($composer['extra']['laravel']['providers'])
        ->toContain(InertiaAgentKitServiceProvider::class);
});

it('publishes the package config file', function (): void {
    $source = realpath(dirname(__DIR__, 2).'/config/inertia-agent-kit.php');

    expect(InertiaAgentKitServiceProvider::pathsToPublish(InertiaAgentKitServiceProvider::class, 'inertia-agent-kit-config'))
        ->toHaveKey($source);
});

it('registers the intended artisan command names', function (): void {
    expect(array_keys(Artisan::all()))->toContain(
        'iak:init',
        'iak:make-resource',
        'iak:audit',
        'iak:feedback',
        'iak:handoff',
        'iak:verify',
    );
});

it('registers json-capable command definitions', function (string $command): void {
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
