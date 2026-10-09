<?php

namespace Mnonm\HermesDeployer\Exceptions;

use RuntimeException;

/**
 * Un backup o un restore que no puede seguir. El mensaje es para una persona
 * que lee el log de un deploy: dice qué falta y dónde.
 */
class BackupException extends RuntimeException {}
