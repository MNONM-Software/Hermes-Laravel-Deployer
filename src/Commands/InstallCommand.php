<?php

namespace Mnonm\HermesDeployer\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mnonm\HermesDeployer\Http\HealthController;
use Throwable;

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

        return $this->verify();
    }

    /**
     * Lo que el comando no deja hecho lo tiene que dejar verificado: media
     * adopción en silencio es justo lo que el diseño no quiere. La ruta de salud
     * es la que más pesa —el healthcheck de Hermes compara la versión desplegada
     * contra la pretendida, y sin ruta esa protección no existe—.
     */
    private function verify(): int
    {
        $missing = [];

        if (blank(config('app.version'))) {
            $missing[] = "config/app.php: falta la clave 'version'";
        }

        if (! $this->healthRouteAnswers()) {
            $missing[] = 'routes: GET /health no resuelve';
        }

        if (! $this->claudeMdHasTheFragment()) {
            $missing[] = 'CLAUDE.md: falta el fragmento de convención';
        }

        $this->newLine();

        if ($missing === []) {
            $this->info('Verificado: versión, ruta de salud y fragmento de CLAUDE.md en su lugar.');

            return 0;
        }

        $this->error('El proyecto todavía NO está listo para Hermes. Falta:');

        foreach ($missing as $item) {
            $this->line('  - '.$item);
        }

        return 1;
    }

    private function healthRouteAnswers(): bool
    {
        try {
            $route = Route::getRoutes()->match(Request::create('/health', 'GET'));
        } catch (Throwable) {
            return false;
        }

        // match() también devuelve rutas `fallback`: Laravel las ordena al final
        // y las entrega cuando nada más matcheó. Sin este chequeo, cualquier
        // proyecto con un Route::fallback (SPA, Inertia) pasa el verify sin que
        // /health exista de verdad. Y una /health ajena tampoco sirve: el
        // deployer espera el JSON con la versión que sólo da nuestro controller.
        return ! $route->isFallback && $route->getControllerClass() === HealthController::class;
    }

    private function claudeMdHasTheFragment(): bool
    {
        $claudeMd = base_path('CLAUDE.md');

        return is_file($claudeMd) && str_contains(
            (string) file_get_contents($claudeMd),
            '## Deploy, changelog y versionado'
        );
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
