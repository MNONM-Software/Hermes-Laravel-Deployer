<?php

namespace Mnonm\HermesDeployer\Tests;

use Illuminate\Foundation\Application;
use Mnonm\HermesDeployer\HermesDeployerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom($this->migrationFixturePath());
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [HermesDeployerServiceProvider::class];
    }

    protected function migrationFixturePath(): string
    {
        $dir = sys_get_temp_dir().'/hermes-deployer-migrations';

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        copy(
            __DIR__.'/../stubs/create_deploy_operations_table.php.stub',
            $dir.'/2000_01_01_000000_create_deploy_operations_table.php'
        );

        return $dir;
    }
}
