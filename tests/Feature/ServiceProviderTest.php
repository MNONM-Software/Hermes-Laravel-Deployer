<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Mnonm\HermesDeployer\HermesDeployerServiceProvider;
use Mnonm\HermesDeployer\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_it_boots_inside_a_laravel_application(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(
            HermesDeployerServiceProvider::class
        ));
    }
}
