<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mnonm\HermesDeployer\Tests\TestCase;

class BaselineCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_every_operation_as_run_without_running_it(): void
    {
        $dir = database_path('operations');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            "{$dir}/2026_09_03_090000_backfill_saldo.php",
            "<?php return new class extends \\Mnonm\\HermesDeployer\\Operation { public function handle(bool \$dryRun): void { throw new \\RuntimeException('no tendria que correr'); } };"
        );

        $this->artisan('deploy:baseline')->assertExitCode(0);

        $this->assertDatabaseHas('deploy_operations', [
            'operation' => '2026_09_03_090000_backfill_saldo',
        ]);

        array_map('unlink', glob($dir.'/*.php') ?: []);
    }

    public function test_it_does_not_touch_migrations(): void
    {
        $this->artisan('deploy:baseline')->assertExitCode(0);

        $this->assertDatabaseCount('deploy_operations', 0);
    }
}
