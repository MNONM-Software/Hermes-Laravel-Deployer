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
}
