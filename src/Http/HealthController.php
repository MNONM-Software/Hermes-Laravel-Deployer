<?php

namespace Mnonm\HermesDeployer\Http;

use Illuminate\Http\JsonResponse;

class HealthController
{
    /**
     * Devuelve la versión que está corriendo de verdad. Hermes la compara
     * contra la que la release pretendía dejar: un restart que salió con exit 0
     * pero dejó el container con el código viejo falla acá.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => config('app.version'),
        ]);
    }
}
