<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mnonm\HermesDeployer\HermesDeployerServiceProvider;
use Mnonm\HermesDeployer\StepPlanner;
use Mnonm\HermesDeployer\Tests\Support\ExtraMigrationsProvider;
use Mnonm\HermesDeployer\Tests\TestCase;

class ExtraMigrationPathsTest extends TestCase
{
    use RefreshDatabase;

    private string $extraPath;

    protected function setUp(): void
    {
        $this->extraPath = sys_get_temp_dir().'/hermes-extra-migrations-'.uniqid();
        mkdir($this->extraPath, 0777, true);

        // La ruta se registra vacía: así RefreshDatabase no corre la migración
        // que el test escribe después, y queda pendiente de verdad.
        ExtraMigrationsProvider::$path = $this->extraPath;

        parent::setUp();

        if (! is_dir(database_path('operations'))) {
            mkdir(database_path('operations'), 0777, true);
        }
    }

    protected function tearDown(): void
    {
        ExtraMigrationsProvider::$path = '';

        array_map('unlink', glob($this->extraPath.'/*') ?: []);
        @rmdir($this->extraPath);

        parent::tearDown();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [HermesDeployerServiceProvider::class, ExtraMigrationsProvider::class];
    }

    public function test_it_sees_the_migrations_another_package_registered(): void
    {
        file_put_contents(
            $this->extraPath.'/2026_09_10_100000_add_a_column_from_a_package.php',
            '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void {} };'
        );

        $steps = $this->app->make(StepPlanner::class)->pending();

        $names = [];

        foreach ($steps as $step) {
            foreach ($step->paths as $path) {
                $names[] = basename($path, '.php');
            }
        }

        // Sin esto deploy:run saltea en silencio las migraciones de cualquier
        // paquete: no corren, no hay error, y la release sale verde.
        $this->assertContains('2026_09_10_100000_add_a_column_from_a_package', $names);
    }
}
