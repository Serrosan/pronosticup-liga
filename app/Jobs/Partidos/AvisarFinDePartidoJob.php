<?php

namespace App\Jobs\Partidos;

use App\Models\CalendarioPartido;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\PushSuscripcion;
use App\Services\AvisosPush;
use App\Services\PushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Al acabar un partido, aviso al móvil con el resultado y lo que pronosticó
 * cada jugador:
 *
 *   ⚽ Final: Celta 0-0 Betis
 *   Tu pronóstico: 1-1 · ≈ Diferencia
 *
 * Solo al móvil (no a la campana: serían ~10 avisos por jornada) y solo a
 * quien tiene activada la categoría "partidos" en su perfil (viene apagada).
 * El tipo de acierto es orientativo: los puntos de verdad (cartas incluidas)
 * se calculan al cerrar la jornada.
 *
 * Lo lanza SincronizarPartidosJob al ver el paso a "Jugado". Se manda una
 * sola vez por partido aunque el job se repita.
 */
class AvisarFinDePartidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    private const TIPOS = [
        'exacto' => '★ Exacto',
        'diferencia' => '≈ Diferencia',
        'signo' => '✓ Signo',
        'fallo' => '✕ Fallo',
    ];

    public function __construct(public int $idPartido) {}

    public function handle(PushService $push, AvisosPush $avisos): void
    {
        $partido = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])->find($this->idPartido);

        if (! $partido || $partido->estado !== 'Jugado' || $partido->goles_casa === null || $partido->goles_fuera === null) {
            return;
        }
        if (! $push->configurado()) {
            return;
        }
        // Una sola vez por partido, aunque el job se lance dos veces.
        if (! Cache::add("aviso-fin-partido:{$partido->id}", now()->toIso8601String(), now()->addDays(7))) {
            return;
        }

        $titulo = sprintf(
            '⚽ Final: %s %d-%d %s',
            $this->nombre($partido->equipoLocal),
            $partido->goles_casa,
            $partido->goles_fuera,
            $this->nombre($partido->equipoVisitante),
        );

        $ligas = Liga::where('id_temporada', $partido->id_temporada)->with('usuarios')->get();
        $pronosticos = Pronostico::where('id_partido', $partido->id)->get();
        $conDispositivo = PushSuscripcion::distinct()->pluck('id_usuario')->flip();

        $usuarios = $ligas->flatMap(fn ($liga) => $liga->usuarios)->unique('id');

        foreach ($usuarios as $usuario) {
            if (! $conDispositivo->has($usuario->id)) {
                continue;
            }
            if (! ($avisos->preferencias($usuario)['partidos'] ?? false)) {
                continue;
            }

            $susLigas = $ligas->filter(fn ($liga) => $liga->usuarios->contains('id', $usuario->id));
            $cuerpo = $this->cuerpo($partido, $susLigas, $pronosticos->where('id_usuario', $usuario->id));

            $push->enviarA(
                $usuario,
                $titulo,
                $cuerpo,
                "/jornadas/{$partido->jornada}",
                "partido-{$partido->id}",
                $usuario->unreadNotifications()->count(),
            );
        }
    }

    /** "Tu pronóstico: 1-1 · ≈ Diferencia", o una línea por liga si en cada una puso algo distinto. */
    private function cuerpo(CalendarioPartido $partido, $ligas, $pronosticos): string
    {
        $porLiga = $ligas->mapWithKeys(function ($liga) use ($partido, $pronosticos) {
            $pronostico = $pronosticos->firstWhere('id_liga', $liga->id);

            return [$liga->nombre => $pronostico ? $this->describir($pronostico, $partido) : null];
        });

        $distintos = $porLiga->values()->unique();

        if ($distintos->count() <= 1) {
            $texto = $distintos->first();

            return $texto ? "Tu pronóstico: {$texto}" : 'No lo pronosticaste';
        }

        return $porLiga->map(fn ($texto, $nombreLiga) => "{$nombreLiga}: ".($texto ?? 'sin pronóstico'))->implode("\n");
    }

    private function describir(Pronostico $pronostico, CalendarioPartido $partido): string
    {
        $local = $pronostico->goles_local_predicho;
        $visitante = $pronostico->goles_visitante_predicho;

        if ($local === null || $visitante === null) {
            $signo = ['Local' => '1', 'Empate' => 'X', 'Visitante' => '2'][$pronostico->resultado_1x2] ?? '?';
            $acierta = $pronostico->resultado_1x2 === self::resultado1x2($partido->goles_casa, $partido->goles_fuera);

            return $signo.' · '.self::TIPOS[$acierta ? 'signo' : 'fallo'];
        }

        $tipo = self::tipoDeAcierto((int) $local, (int) $visitante, (int) $partido->goles_casa, (int) $partido->goles_fuera);

        return "{$local}-{$visitante} · ".self::TIPOS[$tipo];
    }

    /** Mismo criterio que el cierre de jornada (JornadaController) y que tiposAcierto.js. */
    public static function tipoDeAcierto(int $localPred, int $visitantePred, int $casa, int $fuera): string
    {
        if ($localPred === $casa && $visitantePred === $fuera) {
            return 'exacto';
        }

        $real = self::resultado1x2($casa, $fuera);
        if (self::resultado1x2($localPred, $visitantePred) !== $real) {
            return 'fallo';
        }

        if ($real === 'Empate') {
            return abs($localPred - $casa) === 1 ? 'diferencia' : 'signo';
        }

        return ($localPred - $visitantePred) === ($casa - $fuera) ? 'diferencia' : 'signo';
    }

    private static function resultado1x2(int $local, int $visitante): string
    {
        return $local > $visitante ? 'Local' : ($local < $visitante ? 'Visitante' : 'Empate');
    }

    private function nombre($equipo): string
    {
        return (string) ($equipo?->nombre_corto ?: $equipo?->nombre ?: '?');
    }
}
