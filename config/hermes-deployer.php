<?php

return [
    // La ruta que Hermes pide para comparar la versión desplegada contra la
    // que la release pretendía dejar. Tiene que ser **alcanzable desde
    // afuera**: si el proyecto se sirve bajo un prefijo (por ejemplo un
    // location de nginx que sólo deja pasar /ceo), va con ese prefijo
    // (/ceo/health) o el healthcheck da 404 aunque la ruta exista en Laravel.
    'health_path' => env('HERMES_HEALTH_PATH', '/health'),
];
