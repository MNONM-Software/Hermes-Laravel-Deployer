<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Mnonm\HermesDeployer\Tests\TestCase;

class ReleaseCommandTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $backup = [];

    protected function setUp(): void
    {
        parent::setUp();

        // base_path() y config_path() apuntan al skeleton de Testbench, que es
        // real y compartido entre corridas: lo que este test escriba ahí sin
        // devolverlo rompe los tests que corran después.
        foreach ([config_path('app.php'), base_path('CHANGELOG.md')] as $path) {
            $this->backup[$path] = is_file($path) ? (string) file_get_contents($path) : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->backup as $path => $contents) {
            if ($contents === null) {
                @unlink($path);

                continue;
            }

            file_put_contents($path, $contents);
        }

        parent::tearDown();
    }

    public function test_it_writes_the_changelog_entry_and_bumps_the_version(): void
    {
        file_put_contents(base_path('CHANGELOG.md'), "# Changelog\n");
        file_put_contents(config_path('app.php'), "<?php\n\nreturn [\n    'version' => '1.4.0',\n];\n");

        $this->artisan('deploy:release 1.5.0')->assertExitCode(0);

        $this->assertStringContainsString('## v1.5.0', file_get_contents(base_path('CHANGELOG.md')));
        $this->assertStringContainsString("'version' => '1.5.0'", file_get_contents(config_path('app.php')));
    }

    public function test_it_refuses_a_version_that_is_not_semver(): void
    {
        $this->artisan('deploy:release pepe')->assertExitCode(1);
    }

    public function test_it_refuses_to_claim_success_when_config_has_no_version_key(): void
    {
        file_put_contents(base_path('CHANGELOG.md'), "# Changelog\n");
        $configContents = "<?php\n\nreturn [\n    'name' => 'Ceo',\n];\n";
        file_put_contents(config_path('app.php'), $configContents);

        $this->artisan('deploy:release 1.5.0')->assertExitCode(1);

        // No tocó el archivo: no hay clave `version` para reemplazar.
        $this->assertSame($configContents, file_get_contents(config_path('app.php')));

        // El changelog sí queda redactado: es mejor perder el aviso a mano que
        // perder la redacción.
        $this->assertStringContainsString('## v1.5.0', file_get_contents(base_path('CHANGELOG.md')));
    }
}
