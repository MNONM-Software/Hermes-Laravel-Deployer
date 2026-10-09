<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Mnonm\HermesDeployer\Backup\BackupConfig;
use Mnonm\HermesDeployer\Backup\DumpLoader;
use Mnonm\HermesDeployer\Exceptions\BackupException;
use Throwable;
use ZipArchive;

class RestoreCommand extends Command
{
    protected $signature = 'deploy:restore {zip : La ruta del zip que devolvió deploy:backup}';

    protected $description = 'Vacía cada base que respalda spatie y carga su dump desde el zip';

    public function handle(DumpLoader $loader, Filesystem $files): int
    {
        $dir = null;

        try {
            BackupConfig::assertSpatieInstalled();
            BackupConfig::assertRestorable();

            $zipPath = $this->argument('zip');
            $zipPath = is_string($zipPath) ? $zipPath : '';
            $dir = $this->extract($zipPath);
            $dumps = $this->dumps($dir);

            foreach (array_keys($dumps) as $connection) {
                $loader->preflight($connection);
            }

            // Desde acá se toca la base. Todo lo anterior se verificó sin
            // tocarla: un zip que no sirve, o un cliente que no conecta, deja la
            // base como la dejó la falla.
            $restored = [];

            foreach ($dumps as $connection => $dump) {
                try {
                    $wipe = ['--database' => $connection, '--force' => true, '--drop-views' => true];

                    if (BackupConfig::driver($connection) === 'pgsql') {
                        $wipe['--drop-types'] = true;
                    }

                    if ($this->call('db:wipe', $wipe) !== 0) {
                        throw new BackupException("db:wipe falló en la conexión {$connection}.");
                    }

                    $loader->load($connection, $dump);
                } catch (Throwable $e) {
                    // Cualquier cosa, no sólo una BackupException: una
                    // QueryException o un TypeError a mitad de la carga dejan
                    // la base igual de rota, y quien lee tiene que saber cuál.
                    $untouched = array_values(array_diff(array_keys($dumps), $restored, [$connection]));

                    throw new BackupException(
                        "la restauración se cortó en la conexión {$connection}, que pudo quedar vacía o a medio cargar: {$e->getMessage()} "
                        .'Restauradas: '.($restored === [] ? 'ninguna' : implode(', ', $restored)).'. '
                        .'Sin tocar: '.($untouched === [] ? 'ninguna' : implode(', ', $untouched)).'.'
                    );
                }

                $restored[] = $connection;
                $this->info("{$connection}: restaurada desde ".basename($zipPath));
            }

            return 0;
        } catch (BackupException $e) {
            $this->error($e->getMessage());

            return 1;
        } finally {
            if ($dir !== null) {
                $files->deleteDirectory($dir);
            }
        }
    }

    /**
     * Extrae sólo `db-dumps/` a un directorio temporal. Con contraseña, un zip
     * cifrado con otra falla acá, antes del wipe.
     */
    private function extract(string $zipPath): string
    {
        if (! is_file($zipPath)) {
            throw new BackupException("no existe el zip {$zipPath}.");
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new BackupException("no se pudo abrir el zip {$zipPath}: ¿está corrupto?");
        }

        $password = config('backup.backup.password');

        if (is_string($password) && $password !== '') {
            $zip->setPassword($password);
        }

        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_starts_with($name, 'db-dumps/') && ! str_ends_with($name, '/')) {
                $entries[] = $name;
            }
        }

        $dir = sys_get_temp_dir().'/hermes-restore-'.bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);

        if ($entries !== [] && ! $zip->extractTo($dir, $entries)) {
            $zip->close();
            (new Filesystem)->deleteDirectory($dir);

            throw new BackupException('no se pudo extraer el dump del zip: ¿la contraseña de backup.backup.password es la del zip?');
        }

        $zip->close();

        return $dir;
    }

    /**
     * @return array<string, string> conexión => ruta del dump extraído
     */
    private function dumps(string $dir): array
    {
        $dumps = [];

        foreach (BackupConfig::connections() as $connection) {
            $name = BackupConfig::dumpFileName($connection);
            $path = $dir.'/db-dumps/'.$name;

            if (! is_file($path)) {
                throw new BackupException("el zip no tiene el dump de la conexión {$connection} (se esperaba db-dumps/{$name}).");
            }

            if (! is_readable($path) || filesize($path) === 0) {
                throw new BackupException("el dump de la conexión {$connection} está vacío o no se puede leer (db-dumps/{$name}): no se toca la base.");
            }

            $dumps[$connection] = $path;
        }

        if ($dumps === []) {
            throw new BackupException('backup.backup.source.databases está vacío: no hay ninguna base que restaurar.');
        }

        return $dumps;
    }
}
