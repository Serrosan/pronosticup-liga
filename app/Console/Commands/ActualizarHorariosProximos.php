<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use App\Models\Temporada;
use Illuminate\Console\Command;

/**
 * Una vez al día, pide a la API los horarios de las jornadas que vienen, por si
 * ya confirmó los oficiales (antes de eso salen todos a las 00:00).
 *
 * Qué jornadas revisa (solo de la temporada actual, nunca más allá de la 38):
 *  - la PRÓXIMA jornada de verdad y las siguientes (por defecto 3 en total),
 *    calculada con CalendarioPartido::proximaJornadaPorJugar(), que ignora los
 *    partidos aplazados que se juegan lejos de su jornada;
 *  - y, aparte, cualquier jornada anterior con algún partido aún sin jugar
 *    (un aplazado), para recoger su fecha nueva cuando la pongan.
 *
 * Antes empezaba en "la jornada más baja con algo pendiente": con un aplazado
 * de la jornada 6 colgando, revisaba la 6, 7 y 8 y nunca llegaba a la 9.
 */
class ActualizarHorariosProximos extends Command
{
    protected $signature = 'liga:actualizar-horarios-proximos {--jornadas-vista=3}';

    protected $description = 'Revisa una vez al día las próximas jornadas por si la API ya confirmó horarios oficiales, sin esperar a que el partido esté a punto de empezar';

    private const TOTAL_JORNADAS = 38;

    public function handle(): int
    {
        $idTemporada = (int) Temporada::orderByDesc('fecha_inicio')->value('id');
        $vista = max(1, (int) $this->option('jornadas-vista'));

        $revisar = $this->jornadasARevisar($idTemporada, $vista);

        if (! $revisar) {
            $this->info('No hay jornadas pendientes que revisar.');
            return self::SUCCESS;
        }

        foreach ($revisar as $jornada => $motivo) {
            $this->info("--- Revisando jornada {$jornada} ({$motivo}) ---");
            $this->call('liga:forzar-resincronizacion', ['jornada' => $jornada]);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int,string> jornada => por qué se revisa, en orden
     */
    public function jornadasARevisar(int $idTemporada, int $vista): array
    {
        $revisar = [];
        $proxima = CalendarioPartido::proximaJornadaPorJugar($idTemporada);

        // Jornadas anteriores a la próxima con algún partido aún sin jugar (aplazados).
        $conPendientes = CalendarioPartido::where('id_temporada', $idTemporada)
            ->whereIn('estado', ['Programado', 'Aplazado'])
            ->whereBetween('jornada', [1, self::TOTAL_JORNADAS])
            ->when($proxima, fn ($q) => $q->where('jornada', '<', $proxima))
            ->distinct()
            ->orderBy('jornada')
            ->pluck('jornada');

        foreach ($conPendientes as $jornada) {
            $revisar[(int) $jornada] = 'tiene un partido pendiente';
        }

        if ($proxima) {
            for ($jornada = $proxima; $jornada < $proxima + $vista && $jornada <= self::TOTAL_JORNADAS; $jornada++) {
                $revisar[$jornada] = $jornada === $proxima ? 'próxima' : 'de las siguientes';
            }
        }

        ksort($revisar);

        return $revisar;
    }
}
