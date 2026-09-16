<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Mnonm\HermesDeployer\Models\DeployOperation;
use Mnonm\HermesDeployer\StepPlanner;

class StatusCommand extends Command
{
    protected $signature = 'deploy:status';

    protected $description = 'Muestra qué corrió y qué falta en esta instalación, en el orden real de ejecución';

    public function handle(StepPlanner $planner): int
    {
        $ran = DeployOperation::query()->orderBy('ran_at')->get();

        $this->info('Operaciones ya corridas en esta instalación: '.$ran->count());

        foreach ($ran as $operation) {
            $this->line("  {$operation->operation}  ({$operation->ran_at})");
        }

        $pending = $planner->pending();

        $this->newLine();
        $this->info('Pendiente, en orden de ejecución:');

        if ($pending === []) {
            $this->line('  nada');

            return 0;
        }

        foreach ($pending as $step) {
            foreach ($step->paths as $path) {
                $kind = $step->kind === 'migrations' ? 'migración' : 'operación';
                $this->line('  '.basename($path, '.php')."  ({$kind})");
            }
        }

        return 0;
    }
}
