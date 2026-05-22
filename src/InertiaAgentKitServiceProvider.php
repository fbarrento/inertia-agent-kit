<?php

declare(strict_types=1);

namespace InertiaAgentKit;

use Illuminate\Support\ServiceProvider;
use InertiaAgentKit\Console\AuditCommand;
use InertiaAgentKit\Console\FeedbackCommand;
use InertiaAgentKit\Console\InitCommand;
use InertiaAgentKit\Console\MakeResourceCommand;
use InertiaAgentKit\Console\VerifyCommand;

final class InertiaAgentKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'inertia-agent-kit');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            $this->configPath() => config_path('inertia-agent-kit.php'),
        ], 'inertia-agent-kit-config');

        $this->commands([
            InitCommand::class,
            MakeResourceCommand::class,
            AuditCommand::class,
            FeedbackCommand::class,
            VerifyCommand::class,
        ]);
    }

    private function configPath(): string
    {
        return realpath(__DIR__.'/../config/inertia-agent-kit.php') ?: __DIR__.'/../config/inertia-agent-kit.php';
    }
}
