<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Mnonm\HermesDeployer\DeployRunner;
use Mnonm\HermesDeployer\StepPlanner;

class RunCommand extends Command
{
    protected $signature = 'deploy:run
        {--dry-run : Corre las operaciones en seco, sin escribir nada y sin aplicar migraciones}
        {--baseline : Instalación nueva: aplica las migraciones y marca las operaciones como corridas SIN ejecutarlas}';

    protected $description = 'Corre las migraciones y las operaciones de datos pendientes de esta instalación, en orden';

    public function handle(StepPlanner $planner, DeployRunner $runner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $baseline = (bool) $this->option('baseline');

        // Son dos pedidos contradictorios: el baseline escribe el ledger y el
        // seco existe justamente para no escribir nada. Adivinar cuál gana
        // sería peor que rechazar los dos.
        if ($dryRun && $baseline) {
            $this->error('--dry-run y --baseline se excluyen: uno no escribe nada y el otro escribe el ledger.');

            return 1;
        }

        return $runner->run(
            steps: $planner->pending(),
            dryRun: $dryRun,
            report: fn (string $line) => $this->line($line),
            baseline: $baseline,
        );
    }
}
