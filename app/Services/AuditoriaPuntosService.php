<?php

namespace App\Services;

use App\Http\Controllers\Api\V1\JornadaController;
use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\CierreJornada;
use App\Models\EventoPuntos;
use App\Models\Liga;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de puntos de una jornada: ¿lo que hay guardado coincide con lo que
 * saldría si se calculara ahora mismo?
 *
 * No tiene un cálculo propio (eso sería una segunda versión de las reglas, que
 * tarde o temprano se desviaría). Lanza las MISMAS acciones de recalcular que
 * ya existen —puntos de pronósticos y, si ya se habían calculado, goleadores y
 * cartas de eventos— dentro de una transacción, mira el resultado y la DESHACE
 * siempre. En la base de datos no queda nada cambiado.
 *
 * Como recalcula con la configuración de puntos actual, también sirve de vista
 * previa: si has cambiado una regla, las diferencias son el efecto de aplicarla.
 */
class AuditoriaPuntosService
{
    private const TIPOS_DE_PRONOSTICO = [
        'AciertoExacto' => 'resultado exacto',
        'AciertoDiferencia' => 'diferencia de goles',
        'Acierto1x2' => 'signo',
        'Fallo' => 'fallo',
    ];

    private const OTROS_CONCEPTOS = [
        'BonusPleno' => 'Bonus de pleno',
        'GolesGoleadorElegido' => 'Goleadores',
        'CartaBonoJornada' => 'Carta de toda la jornada',
        'CartaEventoPartido' => 'Carta de goles o tarjetas',
        'CartaDoblePartido' => 'Carta de dos partidos',
    ];

    /**
     * @return array{auditable:bool,motivo:?string,cuadra:bool,con_goleadores:bool,usuarios:int,diferencias:array,cartas:array,total_antes:int,total_despues:int}
     */
    public function auditar(Request $request, Liga $liga, int $jornada): array
    {
        $cierre = CierreJornada::where('id_liga', $liga->id)->where('jornada', $jornada)->where('cerrada', true)->first();

        if (! $cierre) {
            return $this->noAuditable('Esta jornada aún no está cerrada en esta liga: no hay puntos que comprobar.');
        }

        $conGoleadores = $cierre->goleadores_calculados_en !== null;
        $antes = $this->foto($liga->id, $jornada);

        $usuario = $request->user();
        $correoOriginal = config('mail.default');
        $error = null;
        $despues = null;

        $usuario->setRelation('ligaActiva', $liga);
        // Por si alguna parte del recálculo enviara un correo: durante la auditoría
        // los correos se quedan en memoria y no salen.
        if (config('mail.mailers.array')) {
            config(['mail.default' => 'array']);
        }

        DB::beginTransaction();
        try {
            $controlador = app(JornadaController::class);

            $respuesta = $controlador->recalcularPuntos($request, $jornada);
            if ($respuesta->getStatusCode() !== 200) {
                $error = $respuesta->getData(true)['message'] ?? 'No se pudo recalcular.';
            }

            if (! $error && $conGoleadores) {
                $respuesta = $controlador->recalcularEventos($request, $jornada);
                if ($respuesta->getStatusCode() !== 200) {
                    $error = $respuesta->getData(true)['message'] ?? 'No se pudieron recalcular los goleadores.';
                }
            }

            if (! $error) {
                $despues = $this->foto($liga->id, $jornada);
            }
        } finally {
            DB::rollBack(); // SIEMPRE: la auditoría nunca deja nada escrito
            config(['mail.default' => $correoOriginal]);
            $usuario->unsetRelation('ligaActiva');
        }

        if ($error) {
            return $this->noAuditable($error);
        }

        return $this->comparar($liga, $jornada, $antes, $despues, $conGoleadores);
    }

    // ------------------------------------------------------------------

    private function noAuditable(string $motivo): array
    {
        return [
            'auditable' => false, 'motivo' => $motivo, 'cuadra' => false, 'con_goleadores' => false,
            'usuarios' => 0, 'diferencias' => [], 'cartas' => [], 'total_antes' => 0, 'total_despues' => 0,
        ];
    }

    /**
     * Lo que hay ahora mismo de esa jornada: puntos por usuario y concepto, y
     * el estado de las cartas que tenían efecto en ella.
     */
    private function foto(int $idLiga, int $jornada): array
    {
        $puntos = [];

        EventoPuntos::where('id_liga', $idLiga)->where('jornada', $jornada)
            ->get(['id_usuario', 'id_partido', 'tipo_evento', 'puntos'])
            ->each(function ($evento) use (&$puntos) {
                $esPronostico = isset(self::TIPOS_DE_PRONOSTICO[$evento->tipo_evento]);
                // El pronóstico de un partido es UN concepto aunque cambie de tipo (de signo a diferencia, p. ej.).
                $clave = $esPronostico
                    ? 'pronostico:'.$evento->id_partido
                    : $evento->tipo_evento.($evento->id_partido ? ':'.$evento->id_partido : '');

                $actual = $puntos[$evento->id_usuario][$clave] ?? ['puntos' => 0, 'tipo' => $evento->tipo_evento, 'id_partido' => $evento->id_partido];
                $actual['puntos'] += (int) $evento->puntos;
                $actual['tipo'] = $evento->tipo_evento;
                $puntos[$evento->id_usuario][$clave] = $actual;
            });

        $cartas = CartaUsuario::where('id_liga', $idLiga)->where('jornada_efecto', $jornada)
            ->get(['id', 'id_usuario', 'id_tipo_carta', 'estado', 'puntos_generados'])
            ->mapWithKeys(fn ($c) => [$c->id => [
                'id_usuario' => $c->id_usuario, 'id_tipo_carta' => $c->id_tipo_carta,
                'estado' => $c->estado, 'puntos' => (int) $c->puntos_generados,
            ]])->all();

        return ['puntos' => $puntos, 'cartas' => $cartas];
    }

    private function comparar(Liga $liga, int $jornada, array $antes, array $despues, bool $conGoleadores): array
    {
        $idsUsuarios = array_unique([...array_keys($antes['puntos']), ...array_keys($despues['puntos'])]);
        $usuarios = User::whereIn('id', $idsUsuarios)->get()->keyBy('id');
        $partidos = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])
            ->where('id_temporada', $liga->id_temporada)->where('jornada', $jornada)->get()->keyBy('id');

        $nombre = fn (int $id) => ($u = $usuarios->get($id)) ? ($u->nombre_visible ?? $u->name) : "Usuario {$id}";
        $suma = fn (array $conceptos) => array_sum(array_column($conceptos, 'puntos'));

        $diferencias = [];
        $totalAntes = 0;
        $totalDespues = 0;

        foreach ($idsUsuarios as $idUsuario) {
            $a = $antes['puntos'][$idUsuario] ?? [];
            $d = $despues['puntos'][$idUsuario] ?? [];
            $totalAntes += $suma($a);
            $totalDespues += $suma($d);

            $detalle = [];
            foreach (array_unique([...array_keys($a), ...array_keys($d)]) as $clave) {
                $va = $a[$clave] ?? null;
                $vd = $d[$clave] ?? null;

                if (($va['puntos'] ?? null) === ($vd['puntos'] ?? null) && ($va['tipo'] ?? null) === ($vd['tipo'] ?? null)) {
                    continue;
                }

                $detalle[] = [
                    'concepto' => $this->concepto($vd ?? $va, $partidos),
                    'antes' => $va ? $this->valor($va) : 'nada',
                    'despues' => $vd ? $this->valor($vd) : 'nada',
                ];
            }

            if ($detalle) {
                $diferencias[] = [
                    'id_usuario' => $idUsuario,
                    'nombre' => $nombre($idUsuario),
                    'antes' => $suma($a),
                    'despues' => $suma($d),
                    'detalle' => $detalle,
                ];
            }
        }

        usort($diferencias, fn ($x, $y) => abs($y['despues'] - $y['antes']) <=> abs($x['despues'] - $x['antes']));

        $cartas = [];
        foreach ($despues['cartas'] as $id => $cd) {
            $ca = $antes['cartas'][$id] ?? null;
            if ($ca && ($ca['estado'] !== $cd['estado'] || $ca['puntos'] !== $cd['puntos'])) {
                $cartas[] = [
                    'id' => $id,
                    'nombre' => $nombre($cd['id_usuario']),
                    'antes' => str_replace('_', ' ', $ca['estado'])." ({$ca['puntos']} pt)",
                    'despues' => str_replace('_', ' ', $cd['estado'])." ({$cd['puntos']} pt)",
                ];
            }
        }

        return [
            'auditable' => true,
            'motivo' => null,
            'cuadra' => ! $diferencias && ! $cartas,
            'con_goleadores' => $conGoleadores,
            'usuarios' => count($idsUsuarios),
            'diferencias' => $diferencias,
            'cartas' => $cartas,
            'total_antes' => $totalAntes,
            'total_despues' => $totalDespues,
        ];
    }

    private function concepto(array $valor, $partidos): string
    {
        $partido = $valor['id_partido'] ? $partidos->get($valor['id_partido']) : null;
        $etiquetaPartido = $partido
            ? ($partido->equipoLocal->nombre_corto ?? $partido->equipoLocal->nombre ?? '?').' - '.($partido->equipoVisitante->nombre_corto ?? $partido->equipoVisitante->nombre ?? '?')
            : null;

        if (isset(self::TIPOS_DE_PRONOSTICO[$valor['tipo']])) {
            return 'Pronóstico de '.($etiquetaPartido ?? 'un partido');
        }

        $texto = self::OTROS_CONCEPTOS[$valor['tipo']] ?? $valor['tipo'];

        return $etiquetaPartido ? "{$texto} ({$etiquetaPartido})" : $texto;
    }

    private function valor(array $valor): string
    {
        $puntos = "{$valor['puntos']} pt";

        return isset(self::TIPOS_DE_PRONOSTICO[$valor['tipo']])
            ? $puntos.' por '.self::TIPOS_DE_PRONOSTICO[$valor['tipo']]
            : $puntos;
    }
}
