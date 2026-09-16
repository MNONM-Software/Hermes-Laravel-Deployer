<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Mnonm\HermesDeployer\DeployRunner;
use Mnonm\HermesDeployer\StepPlanner;

class RunCommand extends Command
{
    protected $signature = 'deploy:run {--dry-run : Corre las operaciones en seco, sin escribir nada y sin aplicar migraciones}';

    protected $description = 'Corre las migraciones y las operaciones de datos pendientes de esta instalación, en orden';

    public function handle(StepPlanner $planner, DeployRunner $runner): int
    {
        return $runner->run(
            steps: $planner->pending(),
            dryRun: (bool) $this->option('dry-run'),
            report: fn (string $line) => $this->line($line),
        );
    }
}
