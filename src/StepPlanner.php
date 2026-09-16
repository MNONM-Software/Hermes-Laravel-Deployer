<?php

namespace Mnonm\HermesDeployer;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;
use Mnonm\HermesDeployer\Exceptions\CollidingStepNameException;
use Mnonm\HermesDeployer\Exceptions\InvalidStepNameException;
use Mnonm\HermesDeployer\Exceptions\MissingLedgerTableException;
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
        $operations = $this->pendingOperations();

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
            if (! preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}/', $name, $matches)) {
                throw InvalidStepNameException::for($path);
            }

            $timestamps[$name] = $matches[0];
        }

        return $timestamps;
    }

    /** @return array<string, string> nombre => ruta */
    private function pendingMigrations(): array
    {
        // Las rutas vienen por constructor a propósito: Migrator::paths() devuelve
        // sólo las rutas extra registradas, nunca database/migrations.
        $files = $this->migrator->getMigrationFiles($this->migrationPaths);
        $ran = $this->migrator->getRepository()->getRan();

        return array_diff_key($files, array_flip($ran));
    }

    /** @return array<string, string> nombre => ruta */
    private function pendingOperations(): array
    {
        // Sin la tabla no sabemos qué corrió acá, y adivinar sería re-correr
        // backfills sobre datos de producción.
        if (! Schema::hasTable('deploy_operations')) {
            throw MissingLedgerTableException::make();
        }

        $files = [];

        foreach (glob($this->operationsPath.'/*_*.php') ?: [] as $path) {
            $files[basename($path, '.php')] = $path;
        }

        $ran = DeployOperation::query()->pluck('operation')->all();

        return array_diff_key($files, array_flip($ran));
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
