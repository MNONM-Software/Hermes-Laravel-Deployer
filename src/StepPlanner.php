<?php

namespace Mnonm\HermesDeployer;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;
use Mnonm\HermesDeployer\Exceptions\CollidingStepNameException;
use Mnonm\HermesDeployer\Exceptions\InvalidStepNameException;
use Mnonm\HermesDeployer\Exceptions\MissingLedgerTableException;
use Mnonm\HermesDeployer\Exceptions\MissingOperationsDirectoryException;
use Mnonm\HermesDeployer\Models\DeployOperation;

/**
 * Descubre lo pendiente de ESTA instalación y lo devuelve en un solo orden.
 *
 * No ejecuta nada: por eso el orden —que es el riesgo real del proyecto— se
 * puede testear sin artisan y sin correr una sola migración de verdad.
 */
final class StepPlanner
{
    /** @param  list<string>  $migrationPaths */
    public function __construct(
        private readonly Migrator $migrator,
        private readonly array $migrationPaths,
        private readonly string $operationsPath,
    ) {}

    /** @return list<DeployStep> */
    public function pending(): array
    {
        $migrations = $this->pendingMigrations();
        $operations = $this->pendingOperations($migrations);

        // No es el nombre completo lo que puede colisionar: son los timestamps.
        // Dos archivos con igual timestamp y distinta descripción también dejan
        // el orden entre las dos carpetas indefinido.
        $migrationTimestamps = $this->timestampsFor($migrations);
        $operationTimestamps = $this->timestampsFor($operations);

        foreach ($operationTimestamps as $operationName => $timestamp) {
            $collidingMigration = array_search($timestamp, $migrationTimestamps, true);

            if ($collidingMigration !== false) {
                throw CollidingStepNameException::for($collidingMigration, $operationName);
            }
        }

        // El prefijo YYYY_MM_DD_HHMMSS hace que el orden lexicográfico sea el
        // cronológico, igual que en las migraciones de Laravel. Por eso el orden
        // entre las dos carpetas no lo declara nadie: ya está en el nombre.
        $all = $migrations + $operations;
        ksort($all);

        return $this->group($all, $migrations);
    }

    /**
     * @param  array<string, string>  $files  nombre => ruta
     * @return array<string, string> nombre => prefijo de timestamp
     */
    private function timestampsFor(array $files): array
    {
        $timestamps = [];

        foreach ($files as $name => $path) {
            if (! preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $name, $matches)) {
                throw InvalidStepNameException::for($path);
            }

            $timestamps[$name] = $matches[1];
        }

        return $timestamps;
    }

    /** @return array<string, string> nombre => ruta */
    private function pendingMigrations(): array
    {
        // Las rutas vienen por constructor a propósito: Migrator::paths() devuelve
        // sólo las rutas extra que registró algún provider, nunca
        // database/migrations. Las dos tienen que estar: quien arma el planner
        // las une.
        $files = $this->migrator->getMigrationFiles($this->migrationPaths);
        $ran = $this->migrator->getRepository()->getRan();

        return array_diff_key($files, array_flip($ran));
    }

    /**
     * @param  array<string, string>  $pendingMigrations
     * @return array<string, string> nombre => ruta
     */
    private function pendingOperations(array $pendingMigrations): array
    {
        $files = $this->operationFiles();

        if (! Schema::hasTable('deploy_operations')) {
            // Si la migración que crea la tabla está pendiente, el ledger está
            // vacío por definición y todo es pendiente: no se adivina, se sabe.
            // Es la primera release de un proyecto que acaba de adoptar la
            // librería, y morir acá es morir con la app apagada.
            if ($this->ledgerMigrationIsPending($pendingMigrations)) {
                return $files;
            }

            // Sin la tabla y sin nadie que la vaya a crear no sabemos qué corrió
            // acá, y adivinar sería re-correr backfills sobre datos de producción.
            throw MissingLedgerTableException::make();
        }

        $ran = DeployOperation::query()->pluck('operation')->all();

        return array_diff_key($files, array_flip($ran));
    }

    /**
     * Todos los `.php` del directorio, sin filtrar por forma del nombre: el que
     * no sigue la convención lo tiene que rechazar InvalidStepNameException, no
     * un glob que lo esconde.
     *
     * @return array<string, string> nombre => ruta
     */
    private function operationFiles(): array
    {
        if (! is_dir($this->operationsPath)) {
            throw MissingOperationsDirectoryException::for($this->operationsPath);
        }

        $files = [];

        foreach (glob($this->operationsPath.'/*.php') ?: [] as $path) {
            $files[basename($path, '.php')] = $path;
        }

        return $files;
    }

    /** @param  array<string, string>  $pendingMigrations */
    private function ledgerMigrationIsPending(array $pendingMigrations): bool
    {
        foreach (array_keys($pendingMigrations) as $name) {
            if (str_ends_with($name, '_create_deploy_operations_table')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Agrupa las migraciones consecutivas en un solo paso: un arranque de
     * artisan por archivo es caro y no compra nada. El orden se preserva.
     *
     * @param  array<string, string>  $all
     * @param  array<string, string>  $migrations
     * @return list<DeployStep>
     */
    private function group(array $all, array $migrations): array
    {
        $steps = [];
        $batch = [];

        foreach ($all as $name => $path) {
            if (isset($migrations[$name])) {
                $batch[] = $path;

                continue;
            }

            if ($batch !== []) {
                $steps[] = DeployStep::migrations($batch);
                $batch = [];
            }

            $steps[] = DeployStep::operation($path);
        }

        if ($batch !== []) {
            $steps[] = DeployStep::migrations($batch);
        }

        return $steps;
    }
}
