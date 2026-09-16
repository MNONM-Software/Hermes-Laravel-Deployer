<?php

namespace Mnonm\HermesDeployer\Exceptions;

use RuntimeException;

class CollidingStepNameException extends RuntimeException
{
    public static function for(string $name): self
    {
        return new self(
            "Hay una migración y una operación con el mismo nombre ({$name}). ".
            'El orden entre las dos queda indefinido: renombrá una de las dos.'
        );
    }
}
