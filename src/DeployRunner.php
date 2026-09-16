<?php

namespace Mnonm\HermesDeployer;

use Mnonm\HermesDeployer\Models\DeployOperation;
use Throwable;

/**
 * Camina la lista que arma el planner. No descubre nada y no sabe ejecutar
 * nada: delega en el StepExecutor, que es lo que permite testear la secuencia
 * sin correr una sola migración de verdad.
 */
final class DeployRunner
{
    public function __construct(private readonly StepExecutor $executor) {}

    /**
     * @param  list<DeployStep>  $steps
     * @param  callable(string): void  $report
     * @return int código de salida: 0 bien, 1 fallo, 2 seco que no pudo terminar
     */
    public function run(array $steps, bool $dryRun, callable $report): int
    {
        if ($steps === []) {
            $report('No hay nada pendiente.');

            return 0;
        }

        $migrationsArePending = false;

        foreach ($steps as $step) {
            if ($step->kind === 'migrations') {
                if ($dryRun) {
                    // En seco no se aplica ninguna migración: aplicarla no es
                    // reversible, que es justo lo que el seco viene a evitar.
                    $migrationsArePending = true;
                    $report('migraciones pendientes (en seco no se aplican): '.$this->names($step));

                    continue;
                }

                if (! $this->attempt(fn () => $this->executor->migrate($step->paths), $this->names($step), $report)) {
                    return 1;
                }

                continue;
            }

            /** @var string $name */
            $name = $step->name;

            if ($dryRun && $migrationsArePending) {
                $report("{$name}: no evaluable en seco, depende de migraciones pendientes. Se corta acá.");

                // Distinto de 0: un seco que se cortó sin evaluar nada no puede
                // ser indistinguible de uno completo, o un job de CI lo toma por
                // bueno.
                return 2;
            }

            $work = function () use ($step, $dryRun, $name): void {
                $this->executor->operation($step->paths[0], $dryRun);

                if ($dryRun) {
                    return;
                }

                // La fila se escribe dentro del attempt: si no se puede escribir,
                // la línea del log no tiene que haber dicho `ok` antes.
                // No usamos el ::create() mágico: sin Larastan, PHPStan no
                // reconoce ese método estático de Eloquent (staticMethod.notFound).
                (new DeployOperation)->fill([
                    'operation' => $name,
                    'ran_at' => now(),
                    'app_version' => config('app.version'),
                ])->save();
            };

            if (! $this->attempt($work, $name, $report)) {
                return 1;
            }
        }

        return 0;
    }

    /** @param  callable(): void  $work */
    private function attempt(callable $work, string $label, callable $report): bool
    {
        try {
            $work();
        } catch (Throwable $e) {
            $report("{$label}: FALLÓ — {$e->getMessage()}");

            return false;
        }

        $report("{$label}: ok");

        return true;
    }

    private function names(DeployStep $step): string
    {
        return implode(', ', array_map(
            static fn (string $path): string => basename($path, '.php'),
            $step->paths
        ));
    }
}
