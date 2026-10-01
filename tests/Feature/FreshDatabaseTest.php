<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Mnonm\HermesDeployer\Tests\TestCase;

/**
 * Sin RefreshDatabase a propósito: es la base de una instalación nueva, en la
 * que todavía no existe ni la tabla `migrations`. Es exactamente lo que
 * encuentra el primer `deploy:run` de un aprovisionamiento.
 */
class FreshDatabaseTest extends TestCase
{
    private string $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        // Testbench deja creada la tabla `migrations` al arrancar: se borra
        // todo para que la base sea la de una instalación recién creada.
        Schema::dropAllTables();

        if (! is_dir(database_path('operations'))) {
            mkdir(database_path('operations'), 0777, true);
        }

        // La migración del ledger donde la deja `hermes:install` en un proyecto
        // real: en database/migrations, que es lo que el planner recorre.
        if (! is_dir(database_path('migrations'))) {
            mkdir(database_path('migrations'), 0777, true);
        }

        $this->ledger = database_path('migrations/2026_01_01_000000_create_deploy_operations_table.php');
        copy(__DIR__.'/../../stubs/create_deploy_operations_table.php.stub', $this->ledger);

        file_put_contents(
            database_path('operations/2026_09_03_090000_backfill_saldo.php'),
            '<?php return new class extends \\Mnonm\\HermesDeployer\\Operation { public function handle(bool $dryRun): void {} };'
        );
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(database_path('operations').'/*.php') ?: []);
        @unlink($this->ledger);

        // El tearDown de Testbench hace rollback de las migraciones que cargó
        // el TestCase, y eso necesita la tabla que este test nunca llega a crear
        // cuando sólo mira el estado.
        $repository = $this->app->make('migrator')->getRepository();

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        parent::tearDown();
    }

    public function test_baseline_on_a_database_without_the_migrations_table_applies_the_schema(): void
    {
        $this->assertFalse(Schema::hasTable('migrations'));

        $this->artisan('deploy:run --baseline')->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('migrations'));
        $this->assertDatabaseHas('deploy_operations', [
            'operation' => '2026_09_03_090000_backfill_saldo',
        ]);
    }

    public function test_a_plain_run_on_a_database_without_the_migrations_table_works_too(): void
    {
        $this->artisan('deploy:run')->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('deploy_operations'));
    }

    public function test_the_status_of_a_fresh_database_lists_everything_as_pending(): void
    {
        $this->artisan('deploy:status')->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('migrations'));
    }
}
