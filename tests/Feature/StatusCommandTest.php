<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mnonm\HermesDeployer\Tests\TestCase;

class StatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El directorio tiene que existir: ausente ya no es lo mismo que vacío,
        // y el InstallCommandTest lo borra del skeleton compartido.
        if (! is_dir(database_path('operations'))) {
            mkdir(database_path('operations'), 0777, true);
        }
    }

    public function test_it_lists_the_pending_steps_without_running_them(): void
    {
        $dir = database_path('operations');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            "{$dir}/2026_09_03_090000_backfill_saldo.php",
            "<?php return new class extends \\Mnonm\\HermesDeployer\\Operation { public function handle(bool \$dryRun): void { throw new \\RuntimeException('no tendria que correr'); } };"
        );

        $this->artisan('deploy:status')
            ->expectsOutputToContain('2026_09_03_090000_backfill_saldo')
            ->assertExitCode(0);

        $this->assertDatabaseCount('deploy_operations', 0);
    }

    protected function tearDown(): void
    {
        // En tearDown y no al final del test: si una aserción falla antes, la
        // limpieza inline no corre y el .php queda en database_path('operations')
        // del skeleton de Testbench, que es real y compartido entre corridas.
        array_map('unlink', glob(database_path('operations').'/*.php') ?: []);

        parent::tearDown();
    }
}
