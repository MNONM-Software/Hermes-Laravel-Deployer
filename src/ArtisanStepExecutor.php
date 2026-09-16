<?php

namespace Mnonm\HermesDeployer;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

final class ArtisanStepExecutor implements StepExecutor
{
    /** @param  list<string>  $paths */
    public function migrate(array $paths): void
    {
        // --realpath porque las rutas vienen absolutas del planner, y --force
        // porque en producción migrate pregunta antes de correr.
        $exit = Artisan::call('migrate', [
            '--path' => $paths,
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException('migrate salió con código '.$exit.': '.Artisan::output());
        }
    }

    public function operation(string $path, bool $dryRun): void
    {
        $operation = require $path;

        if (! $operation instanceof Operation) {
            throw new RuntimeException(
                "El archivo {$path} no devuelve una operación. Tiene que devolver una clase ".
                'anónima que extienda '.Operation::class.'.'
            );
        }

        $operation->handle($dryRun);
    }
}
