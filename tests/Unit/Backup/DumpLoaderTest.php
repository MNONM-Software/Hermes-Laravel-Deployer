<?php

namespace Mnonm\HermesDeployer\Tests\Unit\Backup;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Mnonm\HermesDeployer\Backup\DumpLoader;
use Mnonm\HermesDeployer\Exceptions\BackupException;
use Mnonm\HermesDeployer\Tests\TestCase;

class DumpLoaderTest extends TestCase
{
    private string $dump;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dump = (string) tempnam(sys_get_temp_dir(), 'dump-');
        file_put_contents($this->dump, "CREATE TABLE clientes (id int);\n");

        config()->set('database.connections.app_mysql', [
            'driver' => 'mysql', 'host' => 'mysql', 'port' => 3306,
            'database' => 'artisano', 'username' => 'artisano', 'password' => 's3cr3t',
        ]);
        config()->set('database.connections.app_pg', [
            'driver' => 'pgsql', 'host' => 'db', 'port' => 5432,
            'database' => 'hermes', 'username' => 'hermes', 'password' => 'pg-pass',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->dump);

        parent::tearDown();
    }

    public function test_mysql_gets_the_credentials_from_a_private_file_and_the_dump_by_stdin(): void
    {
        $seen = [];

        Process::fake(function (PendingProcess $process) use (&$seen) {
            $command = (array) $process->command;
            $defaults = substr((string) $command[1], strlen('--defaults-extra-file='));

            $seen = [
                'command' => $command,
                'perms' => fileperms($defaults) & 0777,
                'defaults' => (string) file_get_contents($defaults),
                'stdin' => is_resource($process->input) ? (string) stream_get_contents($process->input) : null,
                'defaults_path' => $defaults,
            ];

            return Process::result();
        });

        (new DumpLoader)->load('app_mysql', $this->dump);

        $this->assertSame('mysql', $seen['command'][0]);
        $this->assertStringStartsWith('--defaults-extra-file=', $seen['command'][1]);
        $this->assertSame('artisano', $seen['command'][2]);
        $this->assertCount(3, $seen['command']);
        $this->assertStringNotContainsString('s3cr3t', implode(' ', $seen['command']));
        $this->assertSame(0600, $seen['perms']);
        $this->assertStringContainsString('password="s3cr3t"', $seen['defaults']);
        $this->assertStringContainsString('host="mysql"', $seen['defaults']);
        $this->assertStringContainsString('port=3306', $seen['defaults']);
        $this->assertStringContainsString('user="artisano"', $seen['defaults']);
        $this->assertSame("CREATE TABLE clientes (id int);\n", $seen['stdin']);
        // El archivo con la contraseña no sobrevive a la carga.
        $this->assertFileDoesNotExist($seen['defaults_path']);
    }

    public function test_it_quotes_a_password_with_quotes_and_backslashes(): void
    {
        config()->set('database.connections.app_mysql.password', 'a"b\\c');
        $defaults = '';

        Process::fake(function (PendingProcess $process) use (&$defaults) {
            $command = (array) $process->command;
            $defaults = (string) file_get_contents(substr((string) $command[1], strlen('--defaults-extra-file=')));

            return Process::result();
        });

        (new DumpLoader)->load('app_mysql', $this->dump);

        $this->assertStringContainsString('password="a\"b\\\\c"', $defaults);
    }

    public function test_postgres_gets_the_password_from_the_environment(): void
    {
        Process::fake();

        (new DumpLoader)->load('app_pg', $this->dump);

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === [
                'psql', '-v', 'ON_ERROR_STOP=1', '--quiet', '--no-psqlrc',
                '-h', 'db', '-p', '5432', '-U', 'hermes', '-d', 'hermes',
            ] && ($process->environment['PGPASSWORD'] ?? null) === 'pg-pass';
        });
    }

    public function test_sqlite_loads_into_the_database_file(): void
    {
        config()->set('database.connections.app_lite', ['driver' => 'sqlite', 'database' => '/tmp/app.sqlite']);
        Process::fake();

        (new DumpLoader)->load('app_lite', $this->dump);

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['sqlite3', '-bail', '/tmp/app.sqlite']);
    }

    public function test_the_mysql_preflight_asks_select_1_with_the_same_private_credentials(): void
    {
        $seen = [];

        Process::fake(function (PendingProcess $process) use (&$seen) {
            $command = (array) $process->command;
            $defaults = substr((string) $command[1], strlen('--defaults-extra-file='));

            $seen = [
                'command' => $command,
                'defaults' => (string) file_get_contents($defaults),
                'stdin' => $process->input,
                'defaults_path' => $defaults,
            ];

            return Process::result();
        });

        (new DumpLoader)->preflight('app_mysql');

        $this->assertSame('mysql', $seen['command'][0]);
        $this->assertStringStartsWith('--defaults-extra-file=', $seen['command'][1]);
        $this->assertSame('artisano', $seen['command'][2]);
        $this->assertCount(3, $seen['command']);
        $this->assertStringNotContainsString('s3cr3t', implode(' ', $seen['command']));
        $this->assertStringContainsString('password="s3cr3t"', $seen['defaults']);
        $this->assertSame('SELECT 1;', $seen['stdin']);
        $this->assertFileDoesNotExist($seen['defaults_path']);
    }

    public function test_the_postgres_preflight_asks_select_1_with_the_password_in_the_environment(): void
    {
        Process::fake();

        (new DumpLoader)->preflight('app_pg');

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === [
                'psql', '-v', 'ON_ERROR_STOP=1', '--quiet', '--no-psqlrc',
                '-h', 'db', '-p', '5432', '-U', 'hermes', '-d', 'hermes',
            ] && ($process->environment['PGPASSWORD'] ?? null) === 'pg-pass'
                && $process->input === 'SELECT 1;';
        });
    }

    public function test_the_sqlite_preflight_asks_select_1_on_the_database_file(): void
    {
        config()->set('database.connections.app_lite', ['driver' => 'sqlite', 'database' => '/tmp/app.sqlite']);
        Process::fake();

        (new DumpLoader)->preflight('app_lite');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['sqlite3', '-bail', '/tmp/app.sqlite']
            && $process->input === 'SELECT 1;');
    }

    public function test_a_preflight_that_fails_says_the_database_was_not_touched(): void
    {
        Process::fake(fn () => Process::result('', "ERROR 1045 (28000): Access denied for user 'artisano'", 1));

        try {
            (new DumpLoader)->preflight('app_mysql');
            $this->fail('se esperaba una BackupException');
        } catch (BackupException $e) {
            $this->assertStringContainsString('no pude conectarme a la conexión app_mysql con mysql antes de restaurar', $e->getMessage());
            $this->assertStringContainsString('Access denied', $e->getMessage());
            $this->assertStringContainsString('no se tocó la base', $e->getMessage());
        }
    }

    public function test_a_client_that_fails_is_an_error_with_its_stderr(): void
    {
        Process::fake(fn () => Process::result('', 'ERROR 1064 (42000) at line 1', 1));

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('ERROR 1064');

        (new DumpLoader)->load('app_mysql', $this->dump);
    }

    public function test_the_mysql_credentials_file_is_removed_even_when_the_load_fails(): void
    {
        $path = '';

        Process::fake(function (PendingProcess $process) use (&$path) {
            $path = substr((string) ((array) $process->command)[1], strlen('--defaults-extra-file='));

            return Process::result('', 'boom', 1);
        });

        try {
            (new DumpLoader)->load('app_mysql', $this->dump);
            $this->fail('se esperaba una BackupException');
        } catch (BackupException) {
            $this->assertFileDoesNotExist($path);
        }
    }
}
