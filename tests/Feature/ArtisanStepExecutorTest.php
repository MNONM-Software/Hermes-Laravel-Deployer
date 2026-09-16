<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mnonm\HermesDeployer\ArtisanStepExecutor;
use Mnonm\HermesDeployer\Tests\TestCase;
use RuntimeException;

class ArtisanStepExecutorTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/hermes-executor-'.uniqid();
        mkdir($this->dir, 0777, true);
    }

    public function test_it_applies_the_migrations_of_the_paths_it_gets(): void
    {
        $path = $this->dir.'/2026_09_02_110000_create_widgets_table.php';
        file_put_contents($path, <<<'PHP'
        <?php
        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;
        return new class extends Migration {
            public function up(): void {
                Schema::create('widgets', fn (Blueprint $t) => $t->id());
            }
        };
        PHP);

        (new ArtisanStepExecutor)->migrate([$path]);

        $this->assertTrue(Schema::hasTable('widgets'));
    }

    public function test_it_throws_when_a_migration_fails(): void
    {
        $path = $this->dir.'/2026_09_02_110000_broken.php';
        file_put_contents($path, <<<'PHP'
        <?php
        use Illuminate\Database\Migrations\Migration;
        return new class extends Migration {
            public function up(): void { throw new \RuntimeException('rota'); }
        };
        PHP);

        $this->expectException(\Throwable::class);

        (new ArtisanStepExecutor)->migrate([$path]);
    }

    public function test_it_runs_an_operation_and_passes_the_dry_run_flag(): void
    {
        $path = $this->dir.'/2026_09_03_090000_records_its_flag.php';
        file_put_contents($path, <<<'PHP'
        <?php
        return new class extends \Mnonm\HermesDeployer\Operation {
            public function handle(bool $dryRun): void {
                file_put_contents(sys_get_temp_dir().'/hermes-flag.txt', $dryRun ? 'dry' : 'wet');
            }
        };
        PHP);

        (new ArtisanStepExecutor)->operation($path, dryRun: true);

        $this->assertSame('dry', file_get_contents(sys_get_temp_dir().'/hermes-flag.txt'));
    }

    public function test_it_throws_when_the_file_does_not_return_an_operation(): void
    {
        $path = $this->dir.'/2026_09_03_090000_not_an_operation.php';
        file_put_contents($path, '<?php return 42;');

        $this->expectException(RuntimeException::class);

        (new ArtisanStepExecutor)->operation($path, dryRun: false);
    }
}
