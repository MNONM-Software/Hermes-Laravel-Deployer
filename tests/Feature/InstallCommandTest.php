<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Mnonm\HermesDeployer\Http\HealthController;
use Mnonm\HermesDeployer\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    /** @return list<string> */
    private function published(): array
    {
        return [
            base_path('deploy.php'),
            base_path('.github/workflows/release.yml'),
            base_path('tests/Feature/DeployStepNamesDoNotCollideTest.php'),
            database_path('operations/.gitkeep'),
            base_path('CLAUDE.md'),
            ...(glob(database_path('migrations/*_create_deploy_operations_table.php')) ?: []),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // El skeleton de Testbench es real y compartido: si el install deja sus
        // archivos ahí, el segundo test de esta clase ya no arranca limpio.
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    private function cleanUp(): void
    {
        foreach ($this->published() as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir(database_path('operations'))) {
            rmdir(database_path('operations'));
        }
    }

    /**
     * Los tres pasos que el comando no hace por vos —no edita archivos ajenos—
     * pero sí verifica al final.
     */
    private function wireTheRest(): void
    {
        config()->set('app.version', '1.0.0');

        Route::get('/health', HealthController::class);

        file_put_contents(
            base_path('CLAUDE.md'),
            (string) file_get_contents(dirname(__DIR__, 2).'/stubs/claude-md-fragment.md.stub')
        );
    }

    public function test_it_fails_when_the_adoption_is_left_half_done(): void
    {
        // Sin la ruta de salud el healthcheck de Hermes no compara versiones:
        // salir 0 acá es dejar la protección apagada sin que nadie se entere.
        config()->set('app.version', null);

        $this->artisan('hermes:install')
            ->expectsOutputToContain('GET /health no resuelve')
            ->assertExitCode(1);
    }

    public function test_it_publishes_everything_a_project_needs(): void
    {
        $this->wireTheRest();

        $this->artisan('hermes:install')->assertExitCode(0);

        $this->assertFileExists(base_path('deploy.php'));
        $this->assertDirectoryExists(database_path('operations'));
        $this->assertFileExists(database_path('operations/.gitkeep'));
        $this->assertFileExists(base_path('.github/workflows/release.yml'));
        $this->assertFileExists(base_path('tests/Feature/DeployStepNamesDoNotCollideTest.php'));

        $this->assertNotEmpty(glob(database_path('migrations/*_create_deploy_operations_table.php')));
    }

    public function test_it_does_not_overwrite_what_is_already_there(): void
    {
        $this->wireTheRest();

        file_put_contents(base_path('deploy.php'), '<?php return ["mio" => true];');

        $this->artisan('hermes:install')->assertExitCode(0);

        $this->assertStringContainsString('mio', file_get_contents(base_path('deploy.php')));
    }

    public function test_running_it_twice_leaves_exactly_one_migration(): void
    {
        $this->wireTheRest();

        $this->artisan('hermes:install')->assertExitCode(0);
        $this->artisan('hermes:install')->assertExitCode(0);

        $this->assertCount(
            1,
            glob(database_path('migrations/*_create_deploy_operations_table.php')) ?: []
        );
    }
}
