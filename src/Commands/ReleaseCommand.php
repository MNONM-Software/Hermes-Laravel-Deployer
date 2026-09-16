<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Mnonm\HermesDeployer\Changelog\ChangelogWriter;
use Mnonm\HermesDeployer\Changelog\CommitReader;
use Mnonm\HermesDeployer\Changelog\VersionSuggester;

class ReleaseCommand extends Command
{
    protected $signature = 'deploy:release {version? : El número. Si no lo pasás, se propone uno y lo confirmás}';

    protected $description = 'Redacta la entrada del changelog y sube el número de versión. No commitea nada';

    public function handle(CommitReader $reader, VersionSuggester $suggester, ChangelogWriter $writer): int
    {
        $read = $reader->sinceLastTag();

        $this->info('Desde '.($read['tag'] ?? 'el principio').' ('.count($read['commits']).' commits):');

        foreach ($read['commits'] as $commit) {
            $this->line("  {$commit}");
        }

        $suggested = $suggester->suggest($read['tag'], $read['commits']);

        $version = $this->argument('version')
            ?? $this->ask('Versión', $suggested);

        if (preg_match('/^\d+\.\d+\.\d+$/', (string) $version) !== 1) {
            $this->error("«{$version}» no es un número de versión. Tiene que ser MAYOR.MEDIO.ÚLTIMO.");

            return 1;
        }

        $changelog = base_path('CHANGELOG.md');
        $existing = is_file($changelog) ? (string) file_get_contents($changelog) : "# Changelog\n";

        file_put_contents($changelog, $writer->prepend(
            $existing,
            $writer->entry($version, date('Y-m-d'), $read['commits'])
        ));

        $config = config_path('app.php');
        $contents = (string) file_get_contents($config);

        $updated = preg_replace(
            "/'version'\s*=>\s*'[^']*'/",
            "'version' => '{$version}'",
            $contents,
            1,
            $replacements
        );

        // Sin esta guarda el comando puede decir que subió el número sin haber
        // tocado nada: un proyecto que todavía no tiene la clave `version` en
        // config/app.php no matchea, y el preg_replace devuelve el archivo igual.
        if ($replacements === 0) {
            $this->error(
                'No encontré la clave `version` en config/app.php. '.
                "Agregale  'version' => '{$version}',  y volvé a correr el comando."
            );

            return 1;
        }

        file_put_contents($config, (string) $updated);

        $this->newLine();
        $this->info("CHANGELOG.md y config/app.php actualizados a {$version}.");
        $this->comment('Revisá los cambios y commiteálos vos: este comando no commitea nada.');

        return 0;
    }
}
