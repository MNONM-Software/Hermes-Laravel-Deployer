<?php

namespace Mnonm\HermesDeployer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * El ledger de operaciones corridas en ESTA instalación. No se commitea:
 * cada cliente lleva la suya, igual que la tabla `migrations`.
 *
 * @property string $operation
 * @property Carbon $ran_at
 * @property string|null $app_version
 */
class DeployOperation extends Model
{
    public $timestamps = false;

    protected $table = 'deploy_operations';

    /** @var list<string> */
    protected $fillable = ['operation', 'ran_at', 'app_version'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ran_at' => 'datetime'];
    }

    /**
     * Escribe la fila del ledger. Vive acá y no en cada llamador porque son
     * dos —el runner cuando una operación termina bien, y el baseline cuando
     * la marca sin ejecutarla— y las dos filas tienen que ser idénticas: una
     * que olvidara la versión haría mentir al `deploy:status`.
     *
     * No se usa el `::create()` mágico: sin Larastan, PHPStan no reconoce ese
     * método estático de Eloquent.
     */
    public static function record(string $operation): void
    {
        (new self)->fill([
            'operation' => $operation,
            'ran_at' => now(),
            'app_version' => config('app.version'),
        ])->save();
    }
}
