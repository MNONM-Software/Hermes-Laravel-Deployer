<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mnonm\HermesDeployer\Tests\BackupTestCase;
use Spatie\Backup\Config\Config;

class BackupCommandTest extends BackupTestCase
{
    public function test_its_last_line_is_the_zip_it_just_made(): void
    {
        DB::connection('backup_test')->statement('create table clientes (id integer primary key, nombre text)');

        $exit = Artisan::call('deploy:backup');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));
        $decoded = json_decode((string) end($lines), true);

        $this->assertIsArray($decoded);
        $this->assertIsString($decoded['zip']);
        $this->assertFileExists($decoded['zip']);
        $this->assertStringStartsWith($this->tmp.'/disk/hermes-test/', $decoded['zip']);
        $this->assertStringNotContainsString('\/', end($lines));
    }

    public function test_it_refuses_a_compressed_dump_before_backing_up_anything(): void
    {
        config()->set('backup.backup.database_dump_compressor', 'Spatie\DbDumper\Compressors\GzipCompressor');

        $exit = Artisan::call('deploy:backup');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('database_dump_compressor', Artisan::output());
        $this->assertDirectoryDoesNotExist($this->tmp.'/disk/hermes-test');
    }

    public function test_it_refuses_a_timestamped_dump_before_backing_up_anything(): void
    {
        config()->set('backup.backup.database_dump_file_timestamp_format', 'Y-m-d');

        $this->assertSame(1, Artisan::call('deploy:backup'));
        $this->assertDirectoryDoesNotExist($this->tmp.'/disk/hermes-test');
    }

    public function test_it_refuses_a_config_without_a_local_disk(): void
    {
        config()->set('filesystems.disks.remote', ['driver' => 's3']);
        config()->set('backup.backup.destination.disks', ['remote']);

        $this->assertSame(1, Artisan::call('deploy:backup'));
        $this->assertStringContainsString('ningún disco', Artisan::output());
    }

    public function test_it_fails_when_the_backup_leaves_no_zip(): void
    {
        // Spatie ignora los nombres de base desconocidos y zipea igual; una base
        // que no se puede volcar, en cambio, hace salir a backup:run con error.
        config()->set('database.connections.roto', ['driver' => 'sqlite', 'database' => '/no/existe/roto.sqlite']);
        config()->set('backup.backup.source.databases', ['roto']);
        // Spatie arma su Config una vez; sin esto no vería lo recién seteado.
        $this->app->forgetInstance(Config::class);

        $exit = Artisan::call('deploy:backup');
        $this->assertSame(1, $exit);
        $this->assertStringNotContainsString('"zip"', Artisan::output());
    }
}
