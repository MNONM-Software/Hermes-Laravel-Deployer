<?php

namespace Mnonm\HermesDeployer\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mnonm\HermesDeployer\DeployRunner;
use Mnonm\HermesDeployer\DeployStep;
use Mnonm\HermesDeployer\Models\DeployOperation;
use Mnonm\HermesDeployer\Tests\Support\RecordingStepExecutor;
use Mnonm\HermesDeployer\Tests\TestCase;

class DeployRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_walks_the_steps_in_order(): void
    {
        $executor = new RecordingStepExecutor;

        $exit = (new DeployRunner($executor))->run([
            DeployStep::migrations(['/m/2026_09_02_110000_add_saldo_column.php', '/m/2026_09_02_140000_add_saldo_index.php']),
            DeployStep::operation('/o/2026_09_03_090000_backfill_saldo.php'),
            DeployStep::migrations(['/m/2026_09_04_100000_make_saldo_not_null.php']),
        ], dryRun: false, report: fn (string $line) => null);

        $this->assertSame(0, $exit);
        $this->assertSame([
            'migrate:2026_09_02_110000_add_saldo_column,2026_09_02_140000_add_saldo_index',
            'op:2026_09_03_090000_backfill_saldo',
            'migrate:2026_09_04_100000_make_saldo_not_null',
        ], $executor->calls);
    }

    public function test_it_records_every_operation_that_finished_well(): void
    {
        config()->set('app.version', '1.5.0');

        (new DeployRunner(new RecordingStepExecutor))->run([
            DeployStep::operation('/o/2026_09_03_090000_backfill_saldo.php'),
        ], dryRun: false, report: fn (string $line) => null);

        $this->assertDatabaseHas('deploy_operations', [
            'operation' => '2026_09_03_090000_backfill_saldo',
            'app_version' => '1.5.0',
        ]);
    }

    public function test_it_does_not_say_ok_when_the_ledger_row_cannot_be_written(): void
    {
        // La fila ya está: el unique de `operation` hace fallar el insert. Antes
        // el log ya había dicho `ok` y después salía un stack trace crudo.
        (new DeployOperation)->fill([
            'operation' => '2026_09_03_090000_backfill_saldo',
            'ran_at' => now(),
        ])->save();

        $lines = [];

        $exit = (new DeployRunner(new RecordingStepExecutor))->run([
            DeployStep::operation('/o/2026_09_03_090000_backfill_saldo.php'),
        ], dryRun: false, report: function (string $line) use (&$lines) {
            $lines[] = $line;
        });

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('FALLÓ', implode("\n", $lines));
        $this->assertStringNotContainsString('ok', implode("\n", $lines));
    }

    public function test_a_failing_operation_stops_everything_after_it(): void
    {
        $executor = new RecordingStepExecutor(failsOn: ['2026_09_03_090000_backfill_saldo']);

        $exit = (new DeployRunner($executor))->run([
            DeployStep::operation('/o/2026_09_03_090000_backfill_saldo.php'),
            DeployStep::operation('/o/2026_09_05_090000_otra_cosa.php'),
        ], dryRun: false, report: fn (string $line) => null);

        $this->assertSame(1, $exit);
        $this->assertSame(['op:2026_09_03_090000_backfill_saldo'], $executor->calls);
        $this->assertDatabaseCount('deploy_operations', 0);
    }

    public function test_a_failing_migration_stops_the_operations_after_it(): void
    {
        $executor = new RecordingStepExecutor(failsOn: ['2026_09_02_110000_add_saldo_column']);

        $exit = (new DeployRunner($executor))->run([
            DeployStep::migrations(['/m/2026_09_02_110000_add_saldo_column.php']),
            DeployStep::operation('/o/2026_09_03_090000_backfill_saldo.php'),
        ], dryRun: false, report: fn (string $line) => null);

        $this->assertSame(1, $exit);
        $this->assertSame(['migrate:2026_09_02_110000_add_saldo_column'], $executor->calls);
    }

    public function test_a_dry_run_writes_no_rows_and_applies_no_migration(): void
    {
        $executor = new RecordingStepExecutor;

        $exit = (new DeployRunner($executor))->run([
            DeployStep::operation('/o/2026_09_03_090000_backfill_saldo.php'),
        ], dryRun: true, report: fn (string $line) => null);

        $this->assertSame(0, $exit);
        $this->assertSame(['dry:2026_09_03_090000_backfill_saldo'], $executor->calls);
        $this->assertDatabaseCount('deploy_operations', 0);
    }

    public function test_a_dry_run_reports_an_operation_that_fails_on_its_own(): void
    {
        $executor = new RecordingStepExecutor(failsOn: ['2026_09_03_090000_backfill_saldo']);

        $exit = (new DeployRunner($executor))->run([
            DeployStep::operation('/o/2026_09_03_090000_backfill_saldo.php'),
            DeployStep::operation('/o/2026_09_05_090000_otra_cosa.php'),
        ], dryRun: true, report: fn (string $line) => null);

        $this->assertSame(1, $exit);
        $this->assertSame(['dry:2026_09_03_090000_backfill_saldo'], $executor->calls);
        $this->assertDatabaseCount('deploy_operations', 0);
    }

    public function test_a_dry_run_stops_at_the_first_operation_behind_a_pending_migration(): void
    {
        $executor = new RecordingStepExecutor;
        $lines = [];

        $exit = (new DeployRunner($executor))->run([
            DeployStep::operation('/o/2026_09_01_090000_evaluable.php'),
            DeployStep::migrations(['/m/2026_09_02_110000_add_saldo_column.php']),
            DeployStep::operation('/o/2026_09_03_090000_backfill_saldo.php'),
        ], dryRun: true, report: function (string $line) use (&$lines) {
            $lines[] = $line;
        });

        // 2 y no 0: el seco no evaluó todo, y quien lo meta en un job de CI
        // tiene que enterarse.
        $this->assertSame(2, $exit);
        $this->assertSame(['dry:2026_09_01_090000_evaluable'], $executor->calls);
        $this->assertStringContainsString('no evaluable en seco', implode("\n", $lines));
        $this->assertStringContainsString('2026_09_03_090000_backfill_saldo', implode("\n", $lines));
    }
}
