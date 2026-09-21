<?php

namespace App\Services;

use App\Models\CartaUsuario;

class MotorFaltas
{
    private const CODIGOS_ESCUDO = ['FAL-PCOM-ESCUDO'];

    /**
     * Registro de Expulsión: cada código bloquea 1 o varias categorías de
     * carta. Añadir una Expulsión nueva (ej. una que bloquee solo Jugadas)
     * es 1 línea aquí, sin tocar nada más.
     */
    private const CATEGORIAS_BLOQUEADAS_POR_EXPULSION = [
        'FAL-LEG-EXPULSION' => ['Jugadas', 'Faltas'],
        'FAL-RAR-EXPULSION' => ['Faltas'],
    ];

    /**
     * ¿El objetivo tiene un Escudo activo protegiéndolo justo en la jornada
     * en la que caería esta Falta? Si lo hay, lo consume (queda resuelto) y
     * lo devuelve — quien llama decide qué hacer con ese dato.
     */
    public function consumirEscudoSiActivo(int $idLiga, int $idUsuarioObjetivo, int $jornadaEfecto): ?CartaUsuario
    {
        $escudo = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idUsuarioObjetivo)
            ->where('jornada_efecto', $jornadaEfecto)
            ->where('estado', 'jugada')
            ->whereHas('tipoCarta', fn ($q) => $q->whereIn('codigo_efecto', self::CODIGOS_ESCUDO))
            ->first();

        if ($escudo) {
            $escudo->update(['estado' => 'resuelta_cumplida', 'puntos_generados' => 0]);
        }

        return $escudo;
    }

    /**
     * Reglas anti-abuso confirmadas: no repetir el mismo rival 2 semanas
     * seguidas, y máximo de Faltas que una persona puede recibir en la misma
     * jornada = mitad de miembros de la liga, redondeado hacia abajo.
     * Devuelve null si no hay ningún problema, o el mensaje de error si lo hay.
     */
    public function comprobarAntiAbuso(int $idLiga, int $idAtacante, int $idObjetivo, int $jornadaEfecto, int $totalMiembrosLiga): ?string
    {
        $rivalRepetido = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario', $idAtacante)
            ->where('id_usuario_objetivo', $idObjetivo)
            ->where('jornada_efecto', $jornadaEfecto - 1)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->exists();

        if ($rivalRepetido) {
            return 'No puedes jugar una Falta contra el mismo rival 2 semanas seguidas.';
        }

        $maximoRecibidas = intdiv($totalMiembrosLiga, 2);

        $yaRecibidas = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario_objetivo', $idObjetivo)
            ->where('jornada_efecto', $jornadaEfecto)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida', 'resuelta_no_cumplida'])
            ->count();

        if ($maximoRecibidas > 0 && $yaRecibidas >= $maximoRecibidas) {
            return "Ese jugador ya ha alcanzado el máximo de Faltas recibidas esta jornada ({$maximoRecibidas}).";
        }

        return null;
    }

    /**
     * ¿Este usuario tiene una Expulsión activa que le impide jugar cartas de
     * esta categoría, justo en esta jornada? Se comprueba con el número de
     * jornada que está a punto de abrirse (donde caería el efecto).
     */
    public function estaExpulsadoDe(int $idLiga, int $idUsuario, int $jornada, string $categoria): bool
    {
        $expulsiones = CartaUsuario::where('id_liga', $idLiga)
            ->where('id_usuario_objetivo', $idUsuario)
            ->where('jornada_efecto', $jornada)
            ->whereIn('estado', ['jugada', 'resuelta_cumplida'])
            ->whereHas('tipoCarta', fn ($q) => $q->whereIn('codigo_efecto', array_keys(self::CATEGORIAS_BLOQUEADAS_POR_EXPULSION)))
            ->with('tipoCarta')
            ->get();

        foreach ($expulsiones as $expulsion) {
            $categoriasBloqueadas = self::CATEGORIAS_BLOQUEADAS_POR_EXPULSION[$expulsion->tipoCarta->codigo_efecto];

            if (in_array($categoria, $categoriasBloqueadas, true)) {
                return true;
            }
        }

        return false;
    }
}