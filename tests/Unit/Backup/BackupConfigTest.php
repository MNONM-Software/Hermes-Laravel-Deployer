<?php

namespace Mnonm\HermesDeployer\Tests\Unit\Backup;

use Mnonm\HermesDeployer\Backup\BackupConfig;
use Mnonm\HermesDeployer\Exceptions\BackupException;
use Mnonm\HermesDeployer\Tests\BackupTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class BackupConfigTest extends BackupTestCase
{
    public function test_the_default_spatie_config_is_restorable(): void
    {
        BackupConfig::assertRestorable();

        $this->assertSame('backups', BackupConfig::localDisk());
        $this->assertSame('hermes-test', BackupConfig::backupName());
        $this->assertSame(['backup_test'], BackupConfig::connections());
    }

    public function test_a_compressed_dump_is_not_restorable(): void
    {
        config()->set('backup.backup.database_dump_compressor', 'Spatie\DbDumper\Compressors\GzipCompressor');

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('backup.backup.database_dump_compressor');

        BackupConfig::assertRestorable();
    }

    public function test_a_timestamped_dump_is_not_restorable(): void
    {
        config()->set('backup.backup.database_dump_file_timestamp_format', 'Y-m-d');

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('backup.backup.database_dump_file_timestamp_format');

        BackupConfig::assertRestorable();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function partialDumpKeys(): array
    {
        return [
            'exclude_tables' => ['exclude_tables'],
            'include_tables' => ['include_tables'],
            'only_tables' => ['only_tables'],
        ];
    }

    #[DataProvider('partialDumpKeys')]
    public function test_a_partial_dump_is_not_restorable(string $key): void
    {
        config()->set("database.connections.backup_test.dump.{$key}", ['clientes']);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage("database.connections.backup_test.dump.{$key}");

        BackupConfig::assertRestorable();
    }

    public function test_an_empty_table_filter_is_restorable(): void
    {
        config()->set('database.connections.backup_test.dump.exclude_tables', []);

        BackupConfig::assertRestorable();

        $this->addToAssertionCount(1);
    }

    public function test_another_dump_extension_is_not_restorable(): void
    {
        config()->set('backup.backup.database_dump_file_extension', 'dump');

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('backup.backup.database_dump_file_extension');

        BackupConfig::assertRestorable();
    }

    public function test_the_sql_extension_is_restorable(): void
    {
        config()->set('backup.backup.database_dump_file_extension', 'sql');
        BackupConfig::assertRestorable();
        config()->set('backup.backup.database_dump_file_extension', null);
        BackupConfig::assertRestorable();

        $this->addToAssertionCount(1);
    }

    public function test_it_needs_a_local_disk(): void
    {
        config()->set('filesystems.disks.remote', ['driver' => 's3']);
        config()->set('backup.backup.destination.disks', ['remote']);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('ningún disco');

        BackupConfig::localDisk();
    }

    public function test_it_takes_the_first_local_disk_of_the_list(): void
    {
        config()->set('filesystems.disks.remote', ['driver' => 's3']);
        config()->set('backup.backup.destination.disks', ['remote', 'backups']);

        $this->assertSame('backups', BackupConfig::localDisk());
    }

    public function test_it_names_each_dump_the_way_spatie_does(): void
    {
        config()->set('database.connections.app_mysql', ['driver' => 'mysql', 'database' => 'artisano']);
        config()->set('database.connections.app_maria', ['driver' => 'mariadb', 'database' => 'artisano']);
        config()->set('database.connections.app_pg', ['driver' => 'pgsql', 'database' => 'hermes']);

        $this->assertSame('mysql-artisano.sql', BackupConfig::dumpFileName('app_mysql'));
        $this->assertSame('mariadb-artisano.sql', BackupConfig::dumpFileName('app_maria'));
        $this->assertSame('postgresql-hermes.sql', BackupConfig::dumpFileName('app_pg'));
        $this->assertSame('sqlite-backup_test-database.sql', BackupConfig::dumpFileName('backup_test'));
    }

    public function test_with_the_connection_as_filename_base_every_dump_is_named_after_its_connection(): void
    {
        config()->set('backup.backup.database_dump_filename_base', 'connection');
        config()->set('database.connections.app_mysql', ['driver' => 'mysql', 'database' => 'artisano']);

        $this->assertSame('mysql-app_mysql.sql', BackupConfig::dumpFileName('app_mysql'));
        $this->assertSame('sqlite-backup_test.sql', BackupConfig::dumpFileName('backup_test'));
    }

    public function test_an_unknown_driver_is_an_error_that_names_the_connection(): void
    {
        config()->set('database.connections.odd', ['driver' => 'sqlsrv', 'database' => 'x']);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('odd');

        BackupConfig::dumpFileName('odd');
    }
}
