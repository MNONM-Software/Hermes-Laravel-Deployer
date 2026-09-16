<?php

namespace Mnonm\HermesDeployer;

/**
 * Un ítem de la lista ordenada que camina el runner: o un grupo de migraciones
 * consecutivas, o una operación suelta.
 */
final class DeployStep
{
    /**
     * @param  'migrations'|'operation'  $kind
     * @param  list<string>  $paths
     */
    private function __construct(
        public readonly string $kind,
        public readonly array $paths,
        public readonly ?string $name,
    ) {}

    /** @param  list<string>  $paths */
    public static function migrations(array $paths): self
    {
        return new self('migrations', $paths, null);
    }

    public static function operation(string $path): self
    {
        return new self('operation', [$path], basename($path, '.php'));
    }
}
