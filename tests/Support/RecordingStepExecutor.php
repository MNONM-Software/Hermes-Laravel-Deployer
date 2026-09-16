<?php

namespace Mnonm\HermesDeployer\Tests\Support;

use Mnonm\HermesDeployer\StepExecutor;
use RuntimeException;

class RecordingStepExecutor implements StepExecutor
{
    /** @var list<string> */
    public array $calls = [];

    /** @param  list<string>  $failsOn  nombres de archivo (sin .php) que tienen que fallar */
    public function __construct(public array $failsOn = []) {}

    /** @param  list<string>  $paths */
    public function migrate(array $paths): void
    {
        $names = array_map(fn (string $p): string => basename($p, '.php'), $paths);

        $this->calls[] = 'migrate:'.implode(',', $names);

        foreach ($names as $name) {
            if (in_array($name, $this->failsOn, true)) {
                throw new RuntimeException("la migración {$name} falló");
            }
        }
    }

    public function operation(string $path, bool $dryRun): void
    {
        $name = basename($path, '.php');

        $this->calls[] = ($dryRun ? 'dry:' : 'op:').$name;

        if (in_array($name, $this->failsOn, true)) {
            throw new RuntimeException("la operación {$name} falló");
        }
    }
}
