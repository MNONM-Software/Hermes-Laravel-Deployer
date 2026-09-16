<?php

namespace Mnonm\HermesDeployer\Exceptions;

use RuntimeException;

class CollidingStepNameException extends RuntimeException
{
    public static function for(string $migrationName, string $operationName): self
    {
        return new self(
            "La migración `{$migrationName}` y la operación `{$operationName}` comparten timestamp. ".
            'El orden entre las dos queda indefinido: cambiá el timestamp de una de las dos.'
        );
    }
}
