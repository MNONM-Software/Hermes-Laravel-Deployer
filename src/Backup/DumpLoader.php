<?php

namespace Mnonm\HermesDeployer\Backup;

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
        /** @var array<string, mixed> $config */
        $config = (array) config("database.connections.{$connection}");

        match (BackupConfig::driver($connection)) {
            'mysql', 'mariadb' => $this->mysql($config, $dumpPath),
            'pgsql' => $this->pgsql($config, $dumpPath),
            default => $this->run(['sqlite3', '-bail', (string) $config['database']], $dumpPath, []),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function mysql(array $config, string $dumpPath): void
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

            $this->run(['mysql', '--defaults-extra-file='.$defaults, (string) $config['database']], $dumpPath, []);
        } finally {
            @unlink($defaults);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function pgsql(array $config, string $dumpPath): void
    {
        $this->run([
            'psql', '-v', 'ON_ERROR_STOP=1', '--quiet', '--no-psqlrc',
            '-h', (string) ($config['host'] ?? '127.0.0.1'),
            '-p', (string) ($config['port'] ?? 5432),
            '-U', (string) ($config['username'] ?? ''),
            '-d', (string) $config['database'],
        ], $dumpPath, ['PGPASSWORD' => (string) ($config['password'] ?? '')]);
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
     * @param  array<string, string>  $env
     */
    private function run(array $argv, string $dumpPath, array $env): void
    {
        $input = fopen($dumpPath, 'rb');

        if ($input === false) {
            throw new BackupException("no se pudo leer el dump {$dumpPath}.");
        }

        try {
            // Sin timeout propio: el límite lo pone quien despliega.
            $result = Process::forever()->env($env)->input($input)->run($argv);
        } finally {
            fclose($input);
        }

        if ($result->failed()) {
            throw new BackupException("{$argv[0]} salió con código {$result->exitCode()}: ".trim($result->errorOutput()));
        }
    }
}
