<?php

namespace Mnonm\HermesDeployer\Exceptions;

use RuntimeException;

class MissingOperationsDirectoryException extends RuntimeException
{
    public static function for(string $path): self
    {
        return new self(
            "No existe el directorio `{$path}`. Corré `php artisan hermes:install`: ".
            'un directorio ausente no es lo mismo que uno vacío, y sin él no hay forma de '.
            'saber si hay arreglos de datos pendientes.'
        );
    }
}
