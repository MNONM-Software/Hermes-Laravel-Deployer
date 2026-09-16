<?php

namespace Mnonm\HermesDeployer;

interface StepExecutor
{
    /**
     * Aplica un grupo de migraciones. Tira si alguna falla.
     *
     * @param  list<string>  $paths
     */
    public function migrate(array $paths): void;

    /** Corre una operación. Tira si falla. */
    public function operation(string $path, bool $dryRun): void;
}
