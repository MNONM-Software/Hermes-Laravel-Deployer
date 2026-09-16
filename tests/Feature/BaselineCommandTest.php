<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mnonm\HermesDeployer\Tests\TestCase;

class BaselineCommandTest extends TestCase
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

    public function test_it_marks_every_operation_as_run_without_running_it(): void
    {
        $this->writeOperation();

        // --force porque Hermes corre sin TTY.
        $this->artisan('deploy:baseline --force')->assertExitCode(0);

        $this->assertDatabaseHas('deploy_operations', [
            'operation' => '2026_09_03_090000_backfill_saldo',
        ]);
    }

    public function test_it_asks_before_marking_and_marks_nothing_if_you_say_no(): void
    {
        $this->writeOperation();

        $this->artisan('deploy:baseline')
            ->expectsOutputToContain('2026_09_03_090000_backfill_saldo')
            ->expectsConfirmation('¿Marcar estas operaciones sin ejecutarlas?', 'no')
            ->assertExitCode(1);

        $this->assertDatabaseCount('deploy_operations', 0);
    }

    private function writeOperation(): void
    {
        file_put_contents(
            database_path('operations/2026_09_03_090000_backfill_saldo.php'),
            "<?php return new class extends \\Mnonm\\HermesDeployer\\Operation { public function handle(bool \$dryRun): void { throw new \\RuntimeException('no tendria que correr'); } };"
        );
    }

    public function test_it_does_not_touch_migrations(): void
    {
        $this->artisan('deploy:baseline')->assertExitCode(0);

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
