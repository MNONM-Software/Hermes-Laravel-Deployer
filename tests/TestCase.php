<?php

namespace Mnonm\HermesDeployer\Tests;

use Illuminate\Foundation\Application;
use Mnonm\HermesDeployer\HermesDeployerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [HermesDeployerServiceProvider::class];
    }
}
