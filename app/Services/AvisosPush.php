<?php

namespace App\Services;

use App\Models\User;

/**
 * Qué notificación de la campana se manda también al móvil, a qué pantalla
 * lleva y si el usuario la quiere. Es la única lista: el perfil la enseña tal
 * cual y el envío la consulta. Añadir un tipo nuevo = una línea en CATEGORIAS.
 */
class AvisosPush
{
    /**
     * clave => [etiqueta, descripción, activada de partida, tipos de notificación, solo superadmin]
     * El tipo null es el de las notificaciones antiguas sin tipo (sincronización fallida, solo admins).
     */
    public const CATEGORIAS = [
        'cierre' => ['⏰ Cierre de jornada', 'Si te faltan pronósticos, goleadores o cartas por jugar antes de que empiece la jornada', true, ['recordatorio_pendiente', 'cartas_sin_jugar', 'jornada_abierta'], false],
        'faltas' => ['⚠️ Te juegan una Falta', 'En cuanto alguien te saca una carta de Falta', true, ['falta_recibida'], false],
        'jornada' => ['📊 Jornada cerrada', 'Tus puntos y tu posición, y cómo les ha ido a tus cartas', true, ['jornada_cerrada', 'cartas_resueltas'], false],
        'cartas' => ['🃏 Cartas nuevas', 'Repartos de cartas y cartas que te da el admin', true, ['cartas_repartidas', 'carta_manual'], false],
        'chat' => ['💬 Te responden o te mencionan', 'Cuando alguien te responde o te menciona en el chat', true, ['chat_respuesta', 'chat_mencion'], false],
        'reacciones' => ['😄 Reacciones en el chat', 'Cuando alguien reacciona a un mensaje tuyo', false, ['chat_reaccion'], false],
        'avisos' => ['📰 Avisos del admin', 'Mensajes que manda el administrador (cambios de horario, novedades…)', true, ['aviso_admin'], false],
        'admin' => ['🛠️ Avisos de administración', 'Jornada lista para cerrar, tareas que fallan y errores nuevos', true, ['admin_jornada_lista', 'admin_tarea_fallida', 'admin_error_nuevo', null], true],
    ];

    public function categoriaDe(?string $tipo, User $usuario): ?string
    {
        foreach (self::CATEGORIAS as $clave => [, , , $tipos, $soloAdmin]) {
            if (in_array($tipo, $tipos, true)) {
                return $soloAdmin && ! $usuario->es_superadmin ? null : $clave;
            }
        }

        return null;
    }

    /** @return array<string,bool> */
    public function preferencias(User $usuario): array
    {
        $guardadas = (array) ($usuario->preferencias_push ?? []);
        $resultado = [];

        foreach (self::CATEGORIAS as $clave => [, , $defecto, , $soloAdmin]) {
            if ($soloAdmin && ! $usuario->es_superadmin) {
                continue;
            }
            $resultado[$clave] = array_key_exists($clave, $guardadas) ? (bool) $guardadas[$clave] : $defecto;
        }

        return $resultado;
    }

    /** Para el perfil: la lista con textos y si está activada. */
    public function paraPerfil(User $usuario): array
    {
        return collect($this->preferencias($usuario))->map(fn ($activa, $clave) => [
            'clave' => $clave,
            'etiqueta' => self::CATEGORIAS[$clave][0],
            'descripcion' => self::CATEGORIAS[$clave][1],
            'activa' => $activa,
        ])->values()->all();
    }

    /** Pantalla a la que lleva tocar el aviso. */
    public function urlDe(?string $tipo, array $datos): string
    {
        $jornada = $datos['jornada'] ?? null;

        return match ($tipo) {
            'recordatorio_pendiente', 'jornada_abierta' => $jornada ? "/jornadas/{$jornada}" : '/dashboard',
            'jornada_cerrada' => $jornada ? "/jornadas/{$jornada}/liga" : '/clasificacion',
            'cartas_sin_jugar', 'falta_recibida', 'cartas_resueltas', 'cartas_repartidas', 'carta_manual' => '/mis-cartas',
            'chat_respuesta', 'chat_mencion', 'chat_reaccion' => '/chat',
            'aviso_admin' => $datos['url'] ?? '/dashboard',
            'admin_jornada_lista' => '/admin/cierre-jornada',
            'admin_error_nuevo' => '/admin/sistema',
            'admin_tarea_fallida', null => '/admin/tareas',
            default => '/dashboard',
        };
    }

    /** Avisos del mismo tipo y jornada se sustituyen en el móvil en vez de apilarse. */
    public function etiquetaDe(?string $tipo, array $datos): ?string
    {
        $jornada = $datos['jornada'] ?? null;

        return match ($tipo) {
            'recordatorio_pendiente', 'jornada_abierta' => $jornada ? "cierre-{$jornada}" : 'cierre',
            'jornada_cerrada' => $jornada ? "jornada-{$jornada}" : 'jornada',
            'chat_respuesta', 'chat_mencion' => 'chat',
            'chat_reaccion' => 'reacciones',
            default => null,
        };
    }
}
