<?php

namespace Mnonm\HermesDeployer\Tests\Support;

use Illuminate\Support\ServiceProvider;

/**
 * Un paquete cualquiera que registra sus propias migraciones, como hacen
 * telescope, horizon o spatie/permission. La ruta queda en migrator->paths().
 */
class ExtraMigrationsProvider extends ServiceProvider
{
    public static string $path = '';

    public function boot(): void
    {
        if (self::$path !== '') {
            $this->loadMigrationsFrom(self::$path);
        }
    }
}
