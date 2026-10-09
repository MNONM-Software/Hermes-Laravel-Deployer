<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mnonm\HermesDeployer\Tests\BackupTestCase;
use Spatie\Backup\Config\Config;
use ZipArchive;

class RestoreCommandTest extends BackupTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('backup_test')->statement('create table clientes (id integer primary key, nombre text)');
        DB::connection('backup_test')->table('clientes')->insert(['nombre' => 'Ana']);
    }

    public function test_it_puts_the_database_back_as_it_was_when_the_backup_was_made(): void
    {
        $zip = $this->backup();

        // Lo que haría una migración rota: cambia filas y crea una tabla.
        DB::connection('backup_test')->table('clientes')->update(['nombre' => 'Roto']);
        DB::connection('backup_test')->statement('create table migracion_rota (id integer)');

        $exit = Artisan::call('deploy:restore', ['zip' => $zip]);
        $this->assertSame(0, $exit, Artisan::output());

        DB::purge('backup_test');

        $this->assertSame('Ana', DB::connection('backup_test')->table('clientes')->value('nombre'));
        $this->assertFalse(Schema::connection('backup_test')->hasTable('migracion_rota'));
    }

    public function test_it_restores_an_encrypted_zip_with_the_configured_password(): void
    {
        config()->set('backup.backup.password', 'buena');
        $this->app->forgetInstance(Config::class);
        $zip = $this->backup();
        DB::connection('backup_test')->table('clientes')->update(['nombre' => 'Roto']);

        $this->assertSame(0, Artisan::call('deploy:restore', ['zip' => $zip]), Artisan::output());

        DB::purge('backup_test');
        $this->assertSame('Ana', DB::connection('backup_test')->table('clientes')->value('nombre'));
    }

    public function test_it_refuses_a_zip_that_does_not_exist_without_touching_the_database(): void
    {
        $this->assertSame(1, Artisan::call('deploy:restore', ['zip' => $this->tmp.'/no-existe.zip']));
        $this->assertStringContainsString('no existe', Artisan::output());
        $this->assertDatabaseUntouched();
    }

    public function test_it_refuses_a_corrupt_zip_without_touching_the_database(): void
    {
        file_put_contents($this->tmp.'/roto.zip', 'esto no es un zip');

        $this->assertSame(1, Artisan::call('deploy:restore', ['zip' => $this->tmp.'/roto.zip']));
        $this->assertDatabaseUntouched();
    }

    public function test_it_refuses_a_wrong_password_without_touching_the_database(): void
    {
        config()->set('backup.backup.password', 'buena');
        $this->app->forgetInstance(Config::class);
        $zip = $this->backup();
        config()->set('backup.backup.password', 'mala');

        $this->assertSame(1, Artisan::call('deploy:restore', ['zip' => $zip]));
        $this->assertStringContainsString('contraseña', Artisan::output());
        $this->assertDatabaseUntouched();
    }

    public function test_it_refuses_a_zip_without_the_dump_of_a_connection_without_touching_the_database(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->tmp.'/sin-dump.zip', ZipArchive::CREATE);
        $zip->addFromString('db-dumps/otra-cosa.sql', 'select 1;');
        $zip->close();

        $this->assertSame(1, Artisan::call('deploy:restore', ['zip' => $this->tmp.'/sin-dump.zip']));
        $this->assertStringContainsString('backup_test', Artisan::output());
        $this->assertDatabaseUntouched();
    }

    private function backup(): string
    {
        $exit = Artisan::call('deploy:backup');
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));
        $decoded = json_decode((string) end($lines), true);

        return is_array($decoded) && is_string($decoded['zip'] ?? null) ? $decoded['zip'] : '';
    }

    /**
     * La base sigue como la dejó el setUp: el comando no llegó al wipe.
     */
    private function assertDatabaseUntouched(): void
    {
        DB::purge('backup_test');

        $this->assertTrue(Schema::connection('backup_test')->hasTable('clientes'));
        $this->assertSame('Ana', DB::connection('backup_test')->table('clientes')->value('nombre'));
    }
}
