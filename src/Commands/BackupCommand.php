<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mnonm\HermesDeployer\Backup\BackupConfig;
use Mnonm\HermesDeployer\Exceptions\BackupException;

class BackupCommand extends Command
{
    protected $signature = 'deploy:backup';

    protected $description = 'Corre backup:run y devuelve en su última línea la ruta del zip que generó';

    public function handle(): int
    {
        try {
            BackupConfig::assertSpatieInstalled();
            BackupConfig::assertRestorable();

            $disk = Storage::disk(BackupConfig::localDisk());
            $before = $this->zips($disk);

            // $this->call y no la fachada: ésta pisaría el buffer de salida del comando.
            $exit = $this->call('backup:run');

            if ($exit !== 0) {
                throw new BackupException('backup:run falló: no hay zip que devolver.');
            }

            // Exactamente uno: con cero no hay qué restaurar, y con más de uno
            // —otro backup corriendo a la vez— elegir sería adivinar.
            $new = array_values(array_diff($this->zips($disk), $before));

            if (count($new) !== 1) {
                throw new BackupException('backup:run tenía que dejar exactamente un zip nuevo en '
                    .BackupConfig::backupName().'/ y dejó '.count($new).'.');
            }

            // La última línea, siempre: es la que lee quien despliega.
            $this->line((string) json_encode(['zip' => $disk->path($new[0])], JSON_UNESCAPED_SLASHES));

            return 0;
        } catch (BackupException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }

    /**
     * @return list<string>
     */
    private function zips(Filesystem $disk): array
    {
        return array_values(array_filter(
            $disk->files(BackupConfig::backupName()),
            static fn (string $file): bool => str_ends_with($file, '.zip'),
        ));
    }
}
