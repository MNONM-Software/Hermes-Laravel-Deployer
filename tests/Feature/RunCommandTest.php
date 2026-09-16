<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mnonm\HermesDeployer\Tests\TestCase;

class RunCommandTest extends TestCase
{
    use RefreshDatabase;

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

    private function writeOperation(string $name): void
    {
        $dir = database_path('operations');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            "{$dir}/{$name}.php",
            '<?php return new class extends \\Mnonm\\HermesDeployer\\Operation { public function handle(bool $dryRun): void {} };'
        );
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob(database_path('operations').'/*.php') ?: []);

        parent::tearDown();
    }
}
