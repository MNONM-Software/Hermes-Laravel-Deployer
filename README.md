# hermes-laravel-deployer

Migraciones y arreglos de datos de deploy **en un solo orden**, más versionado, para proyectos
Laravel gobernados por [Hermes](https://github.com/MNONM-Software/Hermes).

El problema que resuelve: un backfill que tiene que correr *entre* dos migraciones. Como las
operaciones usan el mismo esquema de nombre que las migraciones, el orden entre las dos carpetas
sale del timestamp y nadie lo declara.

## Instalación

```bash
composer require mnonm/hermes-laravel-deployer
php artisan hermes:install
php artisan migrate
```

`hermes:install` publica la migración de la tabla, `database/operations/`, el `deploy.php` que lee
Hermes, el workflow de CI, un test de colisión de nombres y `config/hermes-deployer.php`, y te dice
los cuatro pasos que quedan a mano porque tocan archivos que ya existen. Lo que no deja hecho lo deja
**verificado**: si falta la clave `version` en `config/app.php`, la ruta de salud (`health_path` del
config, `/health` por defecto) o el fragmento de `CLAUDE.md`, el comando los lista y sale con código
distinto de cero.

La ruta de salud es configurable (`health_path` en `config/hermes-deployer.php`, o `HERMES_HEALTH_PATH`
en `.env`) porque tiene que ser alcanzable desde afuera: un proyecto servido bajo un prefijo (por
ejemplo `/ceo`) la registra en `/ceo/health`, no en `/health`.

## Los comandos

| Comando | Qué hace |
| --- | --- |
| `deploy:run` | Corre las migraciones y las operaciones pendientes de esta instalación, en orden. Es lo que corre Hermes |
| `deploy:run --dry-run` | Corre las operaciones en seco. No escribe nada y no aplica migraciones, así que se detiene en la primera operación que dependa de una pendiente, y en ese caso sale con código 2. Herramienta de dev y staging |
| `deploy:run --baseline` | Instalación nueva: aplica las migraciones y marca las operaciones como corridas **sin ejecutarlas**. Un backfill no tiene nada que rellenar contra una base que acaba de nacer, y correr años de backfills en el alta de un cliente es ventana de mantenimiento y riesgo a cambio de nada. Se excluye con `--dry-run` |
| `deploy:status` | Qué corrió y qué falta acá, sin ejecutar nada |
| `deploy:baseline` | Marca todo como corrido sin ejecutarlo. Pregunta antes; `--force` para uso no interactivo. Una vez por instalación, al adoptar el sistema en un proyecto que ya venía andando. Para una instalación nueva no hace falta: eso es `deploy:run --baseline`, que además aplica las migraciones |
| `deploy:release` | Redacta la entrada del `CHANGELOG.md` y sube el número. No commitea nada |

## Una operación

```php
// database/operations/2026_09_03_090000_backfill_saldo.php
return new class extends \Mnonm\HermesDeployer\Operation
{
    public function handle(bool $dryRun): void
    {
        // Idempotente, y no-op donde no aplica.
    }
};
```

El estado es **por instalación**: la tabla `deploy_operations` vive en la base de cada cliente y no
se commitea, igual que la tabla `migrations`.

## Diseño

`docs/superpowers/specs/2026-09-16-hermes-laravel-deployer-design.md`, en el repo de Hermes.
