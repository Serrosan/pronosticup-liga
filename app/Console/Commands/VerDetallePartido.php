<?php

namespace App\Console\Commands;

use App\Models\AlineacionJugador;
use App\Models\CalendarioPartido;
use App\Models\EstadisticaPartido;
use App\Models\EventoPartido;
use App\Models\Jugador;
use Illuminate\Console\Command;

class VerDetallePartido extends Command
{
    protected $signature = 'liga:ver-detalle-partido {id_partido : El id de calendariopartidos}';

    protected $description = 'Muestra alineaciones, estadísticas y eventos guardados de un partido, para verificar a mano que el importador los asoció bien';

    public function handle(): int
    {
        $idPartido = $this->argument('id_partido');
        $partido = CalendarioPartido::with(['equipoLocal', 'equipoVisitante'])->find($idPartido);

        if (! $partido) {
            $this->error("No existe ningún partido con id {$idPartido}.");
            return self::FAILURE;
        }

        $this->info("═══ {$partido->equipoLocal->nombre} {$partido->goles_casa} - {$partido->goles_fuera} {$partido->equipoVisitante->nombre} (Jornada {$partido->jornada}) ═══");
        $this->newLine();

        // --- ALINEACIONES ---
        $alineaciones = AlineacionJugador::where('id_partido', $idPartido)
            ->with('jugador:id,nombre,apellidos,nombre_camiseta')
            ->orderByDesc('titular')
            ->get()
            ->groupBy('id_equipo');

        foreach ([$partido->id_equipo_local => $partido->equipoLocal->nombre, $partido->id_equipo_visitante => $partido->equipoVisitante->nombre] as $idEquipo => $nombreEquipo) {
            $delEquipo = $alineaciones->get($idEquipo, collect());
            $formacion = $delEquipo->first()->formacion ?? '?';

            $this->line("<fg=cyan>{$nombreEquipo}</> — formación {$formacion} — ".$delEquipo->count().' jugadores guardados');

            $titulares = $delEquipo->where('titular', true);
            $suplentes = $delEquipo->where('titular', false);

            $this->line('  Titulares ('.$titulares->count().'):');
            foreach ($titulares as $a) {
                $nombre = $a->jugador->nombre_camiseta ?: trim("{$a->jugador->nombre} {$a->jugador->apellidos}");
                $this->line("    #{$a->dorsal}  {$nombre}");
            }

            $this->line('  Suplentes ('.$suplentes->count().'):');
            foreach ($suplentes as $a) {
                $nombre = $a->jugador->nombre_camiseta ?: trim("{$a->jugador->nombre} {$a->jugador->apellidos}");
                $this->line("    #{$a->dorsal}  {$nombre}");
            }
            $this->newLine();
        }

        // --- ESTADÍSTICAS ---
        $stats = EstadisticaPartido::where('id_partido', $idPartido)->with('equipo:id,nombre_corto')->get();

        $this->line('<fg=cyan>Estadísticas</>');
        foreach ($stats as $s) {
            $this->line("  {$s->equipo->nombre_corto}: posesión {$s->posesion}% · {$s->remates} remates ({$s->efectividad}% efectividad) · {$s->faltas} faltas · {$s->tarjetas_amarillas} amarillas · {$s->tarjetas_rojas} rojas · {$s->corners} córners");
        }
        $this->newLine();

        // --- EVENTOS ---
        $eventos = EventoPartido::where('id_partido', $idPartido)
            ->with('jugador:id,nombre,apellidos,nombre_camiseta')
            ->orderBy('minuto')
            ->get();

        // Los "sale" de las sustituciones se resuelven a mano, en vez de depender
        // de una relación jugadorRelacionado() que no he podido confirmar que exista.
        $idsRelacionados = $eventos->pluck('id_jugador_relacionado')->filter()->unique();
        $jugadoresRelacionados = Jugador::whereIn('id', $idsRelacionados)->get(['id', 'nombre', 'apellidos', 'nombre_camiseta'])->keyBy('id');

        $iconos = ['gol' => '⚽', 'gol_en_propia' => '⚽(pp)', 'tarjeta_amarilla' => '🟨', 'tarjeta_roja' => '🟥', 'sustitucion' => '🔄'];

        $this->line('<fg=cyan>Eventos</> ('.$eventos->count().' en total)');
        foreach ($eventos as $e) {
            $nombreJugador = $e->jugador->nombre_camiseta ?: trim("{$e->jugador->nombre} {$e->jugador->apellidos}");
            $icono = $iconos[$e->tipo_evento] ?? '?';
            $extra = '';
            if ($e->tipo_evento === 'sustitucion' && $e->id_jugador_relacionado && $jugadoresRelacionados->has($e->id_jugador_relacionado)) {
                $sale = $jugadoresRelacionados[$e->id_jugador_relacionado];
                $nombreSale = $sale->nombre_camiseta ?: trim("{$sale->nombre} {$sale->apellidos}");
                $extra = " (sale {$nombreSale})";
            }
            $this->line("  {$e->minuto}'  {$icono}  {$nombreJugador}{$extra}");
        }

        return self::SUCCESS;
    }
}