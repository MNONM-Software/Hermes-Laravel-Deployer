<?php

namespace Mnonm\HermesDeployer;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;
use Mnonm\HermesDeployer\Changelog\CommitReader;
use Mnonm\HermesDeployer\Commands\BaselineCommand;
use Mnonm\HermesDeployer\Commands\InstallCommand;
use Mnonm\HermesDeployer\Commands\ReleaseCommand;
use Mnonm\HermesDeployer\Commands\RunCommand;
use Mnonm\HermesDeployer\Commands\StatusCommand;

class HermesDeployerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/hermes-deployer.php', 'hermes-deployer');

        // El closure se resuelve tarde, después del boot() de los demás
        // providers: para entonces migrator->paths() ya tiene las rutas que
        // cualquier paquete registró con loadMigrationsFrom(). Sin ellas
        // deploy:run saltearía en silencio las migraciones de telescope,
        // horizon, spatie/permission o de un provider del propio proyecto,
        // porque este comando reemplaza a `php artisan migrate` en el deploy.
        $this->app->bind(StepPlanner::class, function ($app) {
            /** @var Migrator $migrator */
            $migrator = $app->make('migrator');

            return new StepPlanner(
                $migrator,
                [...array_values($migrator->paths()), database_path('migrations')],
                database_path('operations'),
            );
        });

        $this->app->bind(StepExecutor::class, ArtisanStepExecutor::class);

        $this->app->bind(
            CommitReader::class,
            fn () => new CommitReader(base_path()),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BaselineCommand::class,
                InstallCommand::class,
                RunCommand::class,
                StatusCommand::class,
                ReleaseCommand::class,
            ]);

            $this->publishes([
                dirname(__DIR__).'/config/hermes-deployer.php' => config_path('hermes-deployer.php'),
            ], 'hermes-deployer-config');
        }
    }
}
