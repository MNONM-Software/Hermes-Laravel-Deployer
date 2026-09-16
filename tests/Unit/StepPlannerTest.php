<?php

namespace Mnonm\HermesDeployer\Tests\Unit;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mnonm\HermesDeployer\Exceptions\CollidingStepNameException;
use Mnonm\HermesDeployer\Exceptions\InvalidStepNameException;
use Mnonm\HermesDeployer\Exceptions\MissingLedgerTableException;
use Mnonm\HermesDeployer\Models\DeployOperation;
use Mnonm\HermesDeployer\StepPlanner;
use Mnonm\HermesDeployer\Tests\TestCase;

class StepPlannerTest extends TestCase
{
    use RefreshDatabase;

    private string $migrationsPath;

    private string $operationsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $root = sys_get_temp_dir().'/hermes-planner-'.uniqid();
        $this->migrationsPath = $root.'/migrations';
        $this->operationsPath = $root.'/operations';
        mkdir($this->migrationsPath, 0777, true);
        mkdir($this->operationsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->migrationsPath));

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function migration(string $name): void
    {
        file_put_contents(
            $this->migrationsPath."/{$name}.php",
            '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void {} };'
        );
    }

    private function operation(string $name): void
    {
        file_put_contents(
            $this->operationsPath."/{$name}.php",
            '<?php return new class extends \\Mnonm\\HermesDeployer\\Operation { public function handle(bool $dryRun): void {} };'
        );
    }

    private function planner(): StepPlanner
    {
        /** @var Migrator $migrator */
        $migrator = $this->app->make('migrator');
        $migrator->setConnection($this->app['config']->get('database.default'));

        return new StepPlanner($migrator, [$this->migrationsPath], $this->operationsPath);
    }

    public function test_it_interleaves_migrations_and_operations_by_timestamp(): void
    {
        $this->migration('2026_09_02_110000_add_saldo_column');
        $this->migration('2026_09_02_140000_add_saldo_index');
        $this->operation('2026_09_03_090000_backfill_saldo');
        $this->migration('2026_09_04_100000_make_saldo_not_null');

        $steps = $this->planner()->pending();

        $this->assertCount(3, $steps);

        $this->assertSame('migrations', $steps[0]->kind);
        $this->assertSame(
            ['2026_09_02_110000_add_saldo_column.php', '2026_09_02_140000_add_saldo_index.php'],
            array_map('basename', $steps[0]->paths)
        );

        $this->assertSame('operation', $steps[1]->kind);
        $this->assertSame('2026_09_03_090000_backfill_saldo', $steps[1]->name);

        $this->assertSame('migrations', $steps[2]->kind);
        $this->assertSame(
            ['2026_09_04_100000_make_saldo_not_null.php'],
            array_map('basename', $steps[2]->paths)
        );
    }

    public function test_it_skips_migrations_that_already_ran(): void
    {
        $this->migration('2026_09_02_110000_add_saldo_column');
        $this->migration('2026_09_04_100000_make_saldo_not_null');

        $this->app->make('migrator')->getRepository()
            ->log('2026_09_02_110000_add_saldo_column', 1);

        $steps = $this->planner()->pending();

        $this->assertCount(1, $steps);
        $this->assertSame(
            ['2026_09_04_100000_make_saldo_not_null.php'],
            array_map('basename', $steps[0]->paths)
        );
    }

    public function test_it_skips_operations_already_recorded_in_this_installation(): void
    {
        $this->operation('2026_09_03_090000_backfill_saldo');
        $this->operation('2026_09_05_090000_backfill_otra_cosa');

        DeployOperation::create([
            'operation' => '2026_09_03_090000_backfill_saldo',
            'ran_at' => now(),
        ]);

        $steps = $this->planner()->pending();

        $this->assertCount(1, $steps);
        $this->assertSame('2026_09_05_090000_backfill_otra_cosa', $steps[0]->name);
    }

    public function test_it_returns_nothing_when_everything_ran(): void
    {
        $this->migration('2026_09_02_110000_add_saldo_column');
        $this->operation('2026_09_03_090000_backfill_saldo');

        $this->app->make('migrator')->getRepository()
            ->log('2026_09_02_110000_add_saldo_column', 1);

        DeployOperation::create([
            'operation' => '2026_09_03_090000_backfill_saldo',
            'ran_at' => now(),
        ]);

        $this->assertSame([], $this->planner()->pending());
    }

    public function test_it_rejects_a_migration_and_an_operation_with_the_same_name(): void
    {
        $this->migration('2026_09_03_090000_same_name');
        $this->operation('2026_09_03_090000_same_name');

        $this->expectException(CollidingStepNameException::class);

        $this->planner()->pending();
    }

    public function test_it_rejects_a_migration_and_an_operation_with_the_same_timestamp_but_different_descriptions(): void
    {
        $this->migration('2026_09_03_090000_make_saldo_not_null');
        $this->operation('2026_09_03_090000_backfill_saldo');

        $this->expectException(CollidingStepNameException::class);

        $this->planner()->pending();
    }

    public function test_it_rejects_a_file_without_a_timestamp_prefix(): void
    {
        $this->operation('backfill_sin_timestamp');

        $this->expectException(InvalidStepNameException::class);

        $this->planner()->pending();
    }

    public function test_it_says_to_migrate_first_when_the_ledger_table_is_missing(): void
    {
        Schema::drop('deploy_operations');

        $this->expectException(MissingLedgerTableException::class);
        $this->expectExceptionMessageMatches('/migrate/');

        $this->planner()->pending();
    }
}
