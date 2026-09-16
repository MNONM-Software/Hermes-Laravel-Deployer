<?php

namespace Mnonm\HermesDeployer;

use Illuminate\Support\ServiceProvider;
use Mnonm\HermesDeployer\Commands\BaselineCommand;
use Mnonm\HermesDeployer\Commands\RunCommand;
use Mnonm\HermesDeployer\Commands\StatusCommand;

class HermesDeployerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StepPlanner::class, fn ($app) => new StepPlanner(
            $app->make('migrator'),
            [database_path('migrations')],
            database_path('operations'),
        ));

        $this->app->bind(StepExecutor::class, ArtisanStepExecutor::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BaselineCommand::class,
                RunCommand::class,
                StatusCommand::class,
            ]);
        }
    }
}
