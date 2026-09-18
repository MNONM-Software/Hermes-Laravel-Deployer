<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mnonm\HermesDeployer\Tests\TestCase;

class RunCommandTest extends TestCase
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

    public function test_it_reports_when_there_is_nothing_pending(): void
    {
        $this->artisan('deploy:run')
            ->expectsOutputToContain('No hay nada pendiente.')
            ->assertExitCode(0);
    }

    public function test_it_runs_a_pending_operation_and_records_it(): void
    {
        $this->writeOperation('2026_09_03_090000_backfill_saldo');

        $this->artisan('deploy:run')->assertExitCode(0);

        $this->assertDatabaseHas('deploy_operations', [
            'operation' => '2026_09_03_090000_backfill_saldo',
        ]);
    }

    public function test_a_dry_run_records_nothing(): void
    {
        $this->writeOperation('2026_09_03_090000_backfill_saldo');

        $this->artisan('deploy:run --dry-run')->assertExitCode(0);

        $this->assertDatabaseCount('deploy_operations', 0);
    }

    public function test_baseline_marks_the_operation_without_executing_it(): void
    {
        $marker = database_path('operations/corrio.txt');
        $this->writeOperation('2026_09_03_090000_backfill_saldo', $marker);

        $this->artisan('deploy:run --baseline')->assertExitCode(0);

        // La prueba de que no se ejecutó no es el log: es que el archivo que
        // la operación escribe cuando corre no existe.
        $this->assertFileDoesNotExist($marker);

        $this->assertDatabaseHas('deploy_operations', [
            'operation' => '2026_09_03_090000_backfill_saldo',
        ]);
    }

    public function test_baseline_and_dry_run_together_are_refused(): void
    {
        // Son dos pedidos contradictorios: uno escribe el ledger y el otro
        // existe para no escribir nada. Adivinar cuál gana sería peor.
        $this->writeOperation('2026_09_03_090000_backfill_saldo');

        $this->artisan('deploy:run --baseline --dry-run')->assertExitCode(1);

        $this->assertDatabaseCount('deploy_operations', 0);
    }

    /**
     * Con `$marker`, la operación escribe ese archivo al correr: es la forma
     * de distinguir "corrió" de "se marcó" sin creerle al log.
     */
    private function writeOperation(string $name, ?string $marker = null): void
    {
        $dir = database_path('operations');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $body = $marker === null
            ? ''
            : 'file_put_contents('.var_export($marker, true).", 'corrio');";

        file_put_contents(
            "{$dir}/{$name}.php",
            '<?php return new class extends \\Mnonm\\HermesDeployer\\Operation { public function handle(bool $dryRun): void { '.$body.' } };'
        );
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(database_path('operations').'/*.php') ?: []);
        array_map('unlink', glob(database_path('operations').'/*.txt') ?: []);

        parent::tearDown();
    }
}
