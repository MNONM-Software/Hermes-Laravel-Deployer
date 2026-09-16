<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Mnonm\HermesDeployer\DeployStep;
use Mnonm\HermesDeployer\Models\DeployOperation;
use Mnonm\HermesDeployer\StepPlanner;

class BaselineCommand extends Command
{
    protected $signature = 'deploy:baseline {--force : No pregunta. Para uso no interactivo, que es como corre Hermes}';

    protected $description = 'Marca todas las operaciones pendientes como ya corridas, SIN ejecutarlas';

    public function handle(StepPlanner $planner): int
    {
        $operations = array_values(array_filter(
            $planner->pending(),
            static fn (DeployStep $step): bool => $step->kind === 'operation'
        ));

        if ($operations === []) {
            $this->info('No hay ninguna operación pendiente: no hay nada que marcar.');

            return 0;
        }

        // El efecto es "estos backfills no corren nunca más", contra la base de
        // un cliente. Se muestra qué, y recién ahí se pregunta.
        $this->warn('Se van a marcar como YA CORRIDAS, sin ejecutarlas, en la base de esta instalación:');

        foreach ($operations as $step) {
            $this->line("  {$step->name}");
        }

        if (! $this->option('force') && ! $this->confirm('¿Marcar estas operaciones sin ejecutarlas?', false)) {
            $this->comment('Cancelado: no se marcó ninguna.');

            return 1;
        }

        foreach ($operations as $step) {
            // fill()->save() y no ::create(): PHPStan nivel 7 sin Larastan no
            // conoce el ::create() mágico de Eloquent. Es lo mismo.
            (new DeployOperation)->fill([
                'operation' => $step->name,
                'ran_at' => now(),
                'app_version' => config('app.version'),
            ])->save();

            $this->line("{$step->name}: marcada como corrida");
        }

        $this->info('Listo: '.count($operations).' operaciones marcadas. No se ejecutó ninguna.');

        return 0;
    }
}
