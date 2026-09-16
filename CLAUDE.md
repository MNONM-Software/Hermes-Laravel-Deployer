# hermes-laravel-deployer

Paquete de composer para Laravel. Le da a un proyecto un comando único —`deploy:run`— que corre las
migraciones y los arreglos de datos de deploy pendientes **de esa instalación**, en el orden que
dictan los timestamps de sus nombres de archivo.

El diseño vive en el repo de Hermes, en
`docs/superpowers/specs/2026-09-16-hermes-laravel-deployer-design.md`, y es la autoridad: cuando el
código y el spec discrepan, gana el spec.

## Convenciones

- PHP 8.3, Laravel ^13. Llaves siempre, tipos explícitos, PHPDoc con array shapes.
- **Todo el código en inglés**: identificadores, clases, métodos, nombres de tests, paths, nombres de
  comandos. Los comentarios y los mensajes de error que ve una persona, **en español**.
- Tests con PHPUnit sobre Orchestra Testbench, clases y métodos `test_snake_case`.
- Antes de cerrar un cambio, las tres en verde: `vendor/bin/phpunit`,
  `vendor/bin/pint --dirty --format agent` y `vendor/bin/phpstan analyse`. O `composer test`.
- **PHPUnit necesita `pdo_sqlite` y `git`.** La base en memoria de Testbench pide el primero y
  `CommitReaderTest` construye repositorios de verdad con el segundo. Si tu PHP no los tiene:

  ```bash
  docker build -f Dockerfile.test -t hermes-deployer-test .
  docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app hermes-deployer-test vendor/bin/phpunit
  ```

## La frontera

**La librería no sabe que Hermes existe.** Ni una llamada de red, ni un socket, ni una referencia a
Hermes fuera de los stubs y del nombre del comando `hermes:install`. Un Laravel con esto instalado y
sin Hermes en ningún lado tiene que seguir siendo un proyecto coherente, que se deploya a mano.

**`StepPlanner` no ejecuta nada y `DeployRunner` no descubre nada.** Esa separación es lo que
permite testear el orden —el riesgo real del proyecto— sin base de datos y sin correr una sola
migración de verdad. No la mezcles.

**`deploy_operations` la toca sólo el runner** (y `deploy:baseline`, que es parte del runner).
