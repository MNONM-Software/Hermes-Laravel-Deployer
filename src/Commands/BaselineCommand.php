<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Mnonm\HermesDeployer\Models\DeployOperation;
use Mnonm\HermesDeployer\StepPlanner;

class BaselineCommand extends Command
{
    protected $signature = 'deploy:baseline';

    protected $description = 'Marca todas las operaciones pendientes como ya corridas, SIN ejecutarlas';

    public function handle(StepPlanner $planner): int
    {
        $marked = 0;

        foreach ($planner->pending() as $step) {
            if ($step->kind !== 'operation') {
                continue;
            }

            // fill()->save() y no ::create(): PHPStan nivel 7 sin Larastan no
            // conoce el ::create() mágico de Eloquent. Es lo mismo.
            (new DeployOperation)->fill([
                'operation' => $step->name,
                'ran_at' => now(),
                'app_version' => config('app.version'),
            ])->save();

            $this->line("{$step->name}: marcada como corrida");
            $marked++;
        }

        $this->info("Listo: {$marked} operaciones marcadas. No se ejecutó ninguna.");

        return 0;
    }
}
