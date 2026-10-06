<?php

namespace App\Services;

/**
 * Los logros secretos: de cada uno el jugador solo ve el título, que hace de
 * pista. Cuando se consigan, cada uno dará una carta (por el sistema de
 * códigos de canje, que ya garantiza una sola vez por persona).
 *
 * Esta es la ÚNICA lista. Para estrenar uno: construir su disparador y poner
 * 'activo' => true. Mientras esté en false sale como "en construcción" y no
 * se puede conseguir.
 */
class LogrosSecretos
{
    public const CATALOGO = [
        'indeciso' => ['titulo' => 'Indeciso', 'icono' => '🌗', 'activo' => false],
        'minuto_93' => ['titulo' => 'Minuto 93', 'icono' => '⏱️', 'activo' => false],
        'pichichi_leyenda' => ['titulo' => 'Pichichi de leyenda', 'icono' => '👑', 'activo' => false],
        'groundhopper' => ['titulo' => 'Groundhopper', 'icono' => '🏟️', 'activo' => false],
        'var' => ['titulo' => 'El VAR lo ha visto', 'icono' => '📺', 'activo' => false],
        'siete_toques' => ['titulo' => 'Siete toques', 'icono' => '👆', 'activo' => false],
        'raton_biblioteca' => ['titulo' => 'Ratón de biblioteca', 'icono' => '📖', 'activo' => false],
        'coleccionista' => ['titulo' => 'Coleccionista', 'icono' => '🗂️', 'activo' => false],
    ];

    /**
     * Lo que ve un jugador. De momento ninguno se puede conseguir: los activos
     * saldrán como "bloqueado" y, más adelante, "conseguido".
     *
     * @return array<int,array{clave:string,titulo:string,icono:string,estado:string}>
     */
    public function lista(): array
    {
        $lista = [];

        foreach (self::CATALOGO as $clave => $logro) {
            $lista[] = [
                'clave' => $clave,
                'titulo' => $logro['titulo'],
                'icono' => $logro['icono'],
                'estado' => $logro['activo'] ? 'bloqueado' : 'en_construccion',
            ];
        }

        return $lista;
    }
}
