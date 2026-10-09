<?php

namespace Mnonm\HermesDeployer\Backup;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Mnonm\HermesDeployer\Exceptions\BackupException;

/**
 * Carga un dump de spatie en su conexión, con el cliente del motor y por
 * stdin. La contraseña nunca va en el argv —se vería en `ps`—: a `mysql` le
 * llega en un archivo 0600 que se borra al terminar, y a `psql` en el entorno.
 */
final class DumpLoader
{
    public function load(string $connection, string $dumpPath): void
    {
        $input = fopen($dumpPath, 'rb');

        if ($input === false) {
            throw new BackupException("no se pudo leer el dump {$dumpPath}.");
        }

        try {
            $result = $this->client($connection, $input);
        } finally {
            fclose($input);
        }

        if ($result->failed()) {
            throw new BackupException(self::binary($connection)." salió con código {$result->exitCode()}: ".trim($result->errorOutput()));
        }
    }

    /**
     * Un `SELECT 1` con el mismo cliente y las mismas credenciales que
     * `load()`. Corre antes del primer `db:wipe`: un cliente que no está
     * instalado o una credencial que no sirve fallan acá, con la base intacta.
     */
    public function preflight(string $connection): void
    {
        $result = $this->client($connection, 'SELECT 1;');

        if ($result->failed()) {
            throw new BackupException(
                "no pude conectarme a la conexión {$connection} con ".self::binary($connection)
                .' antes de restaurar: '.trim($result->errorOutput()).'; no se tocó la base.'
            );
        }
    }

    private static function binary(string $connection): string
    {
        return match (BackupConfig::driver($connection)) {
            'mysql', 'mariadb' => 'mysql',
            'pgsql' => 'psql',
            default => 'sqlite3',
        };
    }

    /**
     * @param  resource|string  $input
     */
    private function client(string $connection, mixed $input): ProcessResult
    {
        /** @var array<string, mixed> $config */
        $config = (array) config("database.connections.{$connection}");

        return match (BackupConfig::driver($connection)) {
            'mysql', 'mariadb' => $this->mysql($config, $input),
            'pgsql' => $this->pgsql($config, $input),
            default => $this->run(['sqlite3', '-bail', (string) $config['database']], $input, []),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  resource|string  $input
     */
    private function mysql(array $config, mixed $input): ProcessResult
    {
        $defaults = tempnam(sys_get_temp_dir(), 'hermes-mysql-');

        if ($defaults === false) {
            throw new BackupException('no se pudo crear el archivo temporal de credenciales de mysql.');
        }

        try {
            chmod($defaults, 0600);

            $lines = [
                '[client]',
                'host='.$this->quoted((string) ($config['host'] ?? '127.0.0.1')),
                'port='.(int) ($config['port'] ?? 3306),
                'user='.$this->quoted((string) ($config['username'] ?? '')),
                'password='.$this->quoted((string) ($config['password'] ?? '')),
            ];

            if (is_string($config['unix_socket'] ?? null) && $config['unix_socket'] !== '') {
                $lines[] = 'socket='.$this->quoted($config['unix_socket']);
            }

            file_put_contents($defaults, implode("\n", $lines)."\n");

            return $this->run(['mysql', '--defaults-extra-file='.$defaults, (string) $config['database']], $input, []);
        } finally {
            @unlink($defaults);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  resource|string  $input
     */
    private function pgsql(array $config, mixed $input): ProcessResult
    {
        return $this->run([
            'psql', '-v', 'ON_ERROR_STOP=1', '--quiet', '--no-psqlrc',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-p', (string) ($config['port'] ?? 5432),
            '-U', (string) ($config['username'] ?? ''),
            '-d', (string) $config['database'],
        ], $input, ['PGPASSWORD' => (string) ($config['password'] ?? '')]);
    }

    /**
     * Un valor de un option file de MySQL, entre comillas dobles y con `\` y
     * `"` escapados: una contraseña con cualquiera de los dos no corta el valor.
     */
    private function quoted(string $value): string
    {
        return '"'.addcslashes($value, '\\"').'"';
    }

    /**
     * @param  list<string>  $argv
     * @param  resource|string  $input
     * @param  array<string, string>  $env
     */
    private function run(array $argv, mixed $input, array $env): ProcessResult
    {
        // Sin timeout propio: el límite lo pone quien despliega.
        return Process::forever()->env($env)->input($input)->run($argv);
    }
}
