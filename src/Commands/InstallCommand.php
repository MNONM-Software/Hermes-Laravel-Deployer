<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Mnonm\HermesDeployer\Http\HealthController;

class InstallCommand extends Command
{
    protected $signature = 'hermes:install';

    protected $description = 'Deja el proyecto con la forma que Hermes sabe leer';

    public function handle(): int
    {
        $stubs = dirname(__DIR__, 2).'/stubs';

        // El nombre lleva un timestamp, así que el guard de copyRaw() —que mira
        // si el archivo destino existe— nunca protegería a la migración: una
        // segunda corrida publicaría otra `create_deploy_operations_table` y el
        // `migrate` siguiente moriría porque la tabla ya está.
        if (glob(database_path('migrations/*_create_deploy_operations_table.php')) === []) {
            $this->copy(
                $stubs.'/create_deploy_operations_table.php.stub',
                database_path('migrations/'.date('Y_m_d_His').'_create_deploy_operations_table.php')
            );
        } else {
            $this->comment('ya existe, no lo toco: la migración de deploy_operations');
        }

        if (! is_dir(database_path('operations'))) {
            mkdir(database_path('operations'), 0777, true);
        }

        $this->copyRaw('', database_path('operations/.gitkeep'));

        $this->copy($stubs.'/deploy.php.stub', base_path('deploy.php'));
        $this->copy($stubs.'/release.yml.stub', base_path('.github/workflows/release.yml'));
        $this->copy(
            $stubs.'/DeployStepNamesDoNotCollideTest.php.stub',
            base_path('tests/Feature/DeployStepNamesDoNotCollideTest.php')
        );

        $this->newLine();
        $this->info('Listo. Lo que falta hacer a mano, porque toca archivos que ya existen:');
        $this->line('  1. config/app.php: agregá  \'version\' => \'1.0.0\'');
        $this->line('  2. routes/web.php: Route::get(\'/health\', '.HealthController::class.');');
        $this->line('  3. CLAUDE.md: pegá el fragmento de '.$stubs.'/claude-md-fragment.md.stub');
        $this->line('  4. php artisan migrate');

        return 0;
    }

    private function copy(string $from, string $to): void
    {
        $this->copyRaw((string) file_get_contents($from), $to);
    }

    private function copyRaw(string $contents, string $to): void
    {
        if (file_exists($to)) {
            $this->comment('ya existe, no lo toco: '.str_replace(base_path().'/', '', $to));

            return;
        }

        $dir = dirname($to);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($to, $contents);

        $this->line('publicado: '.str_replace(base_path().'/', '', $to));
    }
}
