<?php

namespace Mnonm\HermesDeployer\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Mnonm\HermesDeployer\HermesDeployerServiceProvider;
use Spatie\Backup\BackupServiceProvider;

/**
 * Un Laravel con spatie configurado contra una base SQLite en archivo —spatie
 * no puede respaldar una base en memoria— y un disco local temporal.
 */
abstract class BackupTestCase extends TestCase
{
    protected string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/hermes-backup-'.bin2hex(random_bytes(6));
        mkdir($this->tmp.'/disk', 0777, true);
        touch($this->tmp.'/app.sqlite');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem)->deleteDirectory($this->tmp);
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [HermesDeployerServiceProvider::class, BackupServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.connections.backup_test', [
            'driver' => 'sqlite',
            'database' => $this->tmp.'/app.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $config->set('filesystems.disks.backups', ['driver' => 'local', 'root' => $this->tmp.'/disk']);

        $config->set('backup', require dirname(__DIR__).'/vendor/spatie/laravel-backup/config/backup.php');
        $config->set('backup.backup.name', 'hermes-test');
        $config->set('backup.backup.source.files.include', []);
        $config->set('backup.backup.source.databases', ['backup_test']);
        $config->set('backup.backup.destination.disks', ['backups']);
        $config->set('backup.backup.temporary_directory', $this->tmp.'/spatie-temp');
        $config->set('backup.backup.password', null);
        // Sin canales, pero con cada clave: spatie indexa la notificación que dispara.
        $config->set('backup.notifications.notifications', array_map(
            static fn (): array => [],
            $config->get('backup.notifications.notifications'),
        ));
    }
}
