<?php

/*
 * Qué versión está desplegada. Coolify deja estos dos datos en el entorno del
 * contenedor; se leen aquí (y no con env() suelto) para que queden guardados
 * en la caché de configuración que se genera al arrancar, que es lo único que
 * ve la web en producción.
 */
return [
    'commit' => env('SOURCE_COMMIT'),
    'rama' => env('COOLIFY_BRANCH') ? trim((string) env('COOLIFY_BRANCH'), "\"' ") : null,
];
