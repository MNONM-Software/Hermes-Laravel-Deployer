<?php

namespace Mnonm\HermesDeployer;

abstract class Operation
{
    /**
     * Corre el arreglo de datos. Tiene que ser idempotente y no-op donde no
     * aplica: la misma operación viaja a todas las instalaciones.
     *
     * En seco ($dryRun = true) reporta lo que haría y no escribe nada.
     */
    abstract public function handle(bool $dryRun): void;
}
