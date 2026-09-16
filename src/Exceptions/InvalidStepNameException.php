<?php

namespace Mnonm\HermesDeployer\Exceptions;

use RuntimeException;

class InvalidStepNameException extends RuntimeException
{
    public static function for(string $path): self
    {
        return new self(
            "El archivo `{$path}` no sigue la convención `YYYY_MM_DD_HHMMSS_descripcion.php`. ".
            'Sin ese prefijo no hay forma de ubicarlo en el orden de deploy.'
        );
    }
}
