<?php

namespace Mnonm\HermesDeployer\Exceptions;

use RuntimeException;

class MissingLedgerTableException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No existe la tabla `deploy_operations`. Corré `php artisan migrate` primero: '.
            'sin ella no hay forma de saber qué operaciones ya corrieron en esta instalación.'
        );
    }
}
