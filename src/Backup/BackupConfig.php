<?php

namespace Mnonm\HermesDeployer\Backup;

use Mnonm\HermesDeployer\Exceptions\BackupException;

/**
 * Lo que `deploy:backup` y `deploy:restore` leen de la config de spatie y de
 * la app. Las dos formas de nombre de dump que spatie permite y este paquete
 * no sabe leer —comprimido, con timestamp— se rechazan acá, para que el
 * rechazo llegue en el backup, antes de tocar nada, y no en la reversión.
 */
final class BackupConfig
{
    public static function assertSpatieInstalled(): void
    {
        if (! class_exists('Spatie\Backup\BackupServiceProvider')) {
            throw new BackupException('spatie/laravel-backup no está instalado: deploy:backup y deploy:restore lo necesitan.');
        }
    }

    public static function assertRestorable(): void
    {
        if (config('backup.backup.database_dump_compressor') !== null) {
            throw new BackupException('backup.backup.database_dump_compressor tiene que ser null: deploy:restore no sabe leer dumps comprimidos.');
        }

        if (config('backup.backup.database_dump_file_timestamp_format') !== null) {
            throw new BackupException('backup.backup.database_dump_file_timestamp_format tiene que ser null: deploy:restore busca cada dump por su nombre exacto.');
        }

        self::localDisk();
    }

    /**
     * El primer disco local de la lista de destinos: el zip que restaura
     * `deploy:restore` tiene que estar en este servidor.
     */
    public static function localDisk(): string
    {
        foreach ((array) config('backup.backup.destination.disks', []) as $disk) {
            if (is_string($disk) && config("filesystems.disks.{$disk}.driver") === 'local') {
                return $disk;
            }
        }

        throw new BackupException('ningún disco de backup.backup.destination.disks es local: deploy:restore necesita el zip en este servidor.');
    }

    public static function backupName(): string
    {
        return (string) config('backup.backup.name');
    }

    /**
     * @return list<string>
     */
    public static function connections(): array
    {
        return array_values(array_filter(
            (array) config('backup.backup.source.databases', []),
            static fn (mixed $connection): bool => is_string($connection),
        ));
    }

    public static function driver(string $connection): string
    {
        $driver = config("database.connections.{$connection}.driver");

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            $shown = is_string($driver) ? $driver : 'desconocido';

            throw new BackupException("la conexión {$connection} usa el driver {$shown}, que deploy:restore no sabe cargar.");
        }

        return $driver;
    }

    /**
     * El nombre que le pone spatie al dump de una conexión dentro de
     * `db-dumps/`, con la config que `assertRestorable()` deja pasar (sin
     * compresor y sin timestamp).
     */
    public static function dumpFileName(string $connection): string
    {
        $driver = self::driver($connection);

        $type = match ($driver) {
            'pgsql' => 'postgresql',
            default => $driver,
        };

        if (config('backup.backup.database_dump_filename_base') === 'connection') {
            $name = $connection;
        } elseif ($driver === 'sqlite') {
            $name = $connection.'-database';
        } else {
            $name = (string) config("database.connections.{$connection}.database");
        }

        return "{$type}-{$name}.sql";
    }
}
