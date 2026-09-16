<?php

namespace Mnonm\HermesDeployer\Exceptions;

use RuntimeException;

class OperationPredatesLedgerMigrationException extends RuntimeException
{
    public static function for(string $operationPath, string $ledgerMigrationName): self
    {
        return new self(
            "El arreglo de datos `{$operationPath}` tiene un timestamp anterior al de la migración ".
            "`{$ledgerMigrationName}`, que todavía está pendiente. Si se ejecutara así, el runner no ".
            'podría anotarlo en `deploy_operations` porque la tabla todavía no existe, y quedaría '.
            'corrido sin registro. Renombrá el archivo con un timestamp posterior al de esa migración.'
        );
    }
}
