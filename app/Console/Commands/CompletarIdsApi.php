<?php

namespace App\Console\Commands;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Temporada;
use App\Services\ComparadorPlantillasService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Rellena id_externo_api a los jugadores que no lo tienen, buscando SOLO en la
 * plantilla real de su propio equipo en football-data.
 *
 * Más prudente que antes:
 *  - compara también con el nombre de camiseta y los apellidos (no solo con
 *    nombre + apellidos), con el mismo cálculo que Comparar plantillas;
 *  - solo asigna con un 75 % de parecido, o con un 55 % si además comparten
 *    un apellido;
 *  - si hay dos candidatos casi igual de buenos, no elige: lo deja para ti;
 *  - al terminar, lista qué ha asignado a quién, para poder revisarlo.
 * Lo que no asigna se resuelve a mano en Admin → Comparar plantillas.
 *
 * Usa las plantillas que ya consultó Comparar plantillas si son recientes;
 * si no, las pide a football-data (unos 7 segundos por equipo).
 */
class CompletarIdsApi extends Command
{
    protected $signature = 'liga:completar-ids-api
        {--en-seco : Solo enseña lo que haría, sin guardar nada}
        {--umbral=75 : Parecido mínimo (%) para asignar sin más}';

    protected $description = 'Rellena el identificador de football-data a los jugadores que no lo tienen, buscando solo en la plantilla de su equipo';

    /** Con un apellido en común, basta este parecido. */
    private const UMBRAL_CON_APELLIDO = 55;

    /** Si el segundo candidato está a menos de esto del primero, no se elige ninguno. */
    private const MARGEN_EMPATE = 8;

    public function handle(ComparadorPlantillasService $comparador): int
    {
        $enSeco = (bool) $this->option('en-seco');
        $umbral = max(50, min(100, (int) $this->option('umbral')));
        $idTemporada = (int) Temporada::orderByDesc('fecha_inicio')->value('id');

        $equipos = Equipo::whereNotNull('id_equipo_api')->orderBy('nombre')->get();
        $asignados = [];
        $sinResolver = [];
        $fallos = [];

        $this->line($enSeco ? 'EN SECO: no se guarda nada.' : "Umbral: {$umbral} % (o ".self::UMBRAL_CON_APELLIDO.' % con un apellido en común).');
        $barra = $this->output->createProgressBar($equipos->count());
        $barra->start();

        foreach ($equipos as $equipo) {
            try {
                $plantilla = collect($comparador->plantilla($equipo));
            } catch (RuntimeException $e) {
                $fallos[] = "{$equipo->nombre}: {$e->getMessage()}";
                $barra->advance();
                continue;
            }

            $idsUsados = Jugador::whereNotNull('id_externo_api')->pluck('id_externo_api')->map(fn ($id) => (int) $id)->flip();
            $libres = $plantilla->reject(fn ($j) => $idsUsados->has($j['id']))->values();

            $sinId = Jugador::whereHas('plantillasTemporada', fn ($q) => $q->where('id_equipo', $equipo->id)
                ->where('id_temporada', $idTemporada)->whereNull('fecha_salida'))
                ->whereNull('dado_de_baja_en')
                ->whereNull('id_externo_api')
                ->get();

            foreach ($sinId as $jugador) {
                $candidatos = $libres
                    ->map(fn ($c) => $c + [
                        'parecido' => $comparador->parecido($jugador, $c['nombre']),
                        'apellido' => $comparador->compartenApellido($jugador, $c['nombre']),
                    ])
                    ->sortByDesc('parecido')
                    ->values();

                $mejor = $candidatos->first();
                $segundo = $candidatos->get(1);
                $nombre = $comparador->nombreLocal($jugador);
                $vale = $mejor && ($mejor['parecido'] >= $umbral || ($mejor['apellido'] && $mejor['parecido'] >= self::UMBRAL_CON_APELLIDO));
                $empate = $mejor && $segundo && ($mejor['parecido'] - $segundo['parecido']) < self::MARGEN_EMPATE;

                if ($vale && ! $empate) {
                    if (! $enSeco) {
                        $jugador->update(['id_externo_api' => $mejor['id']]);
                    }
                    $libres = $libres->reject(fn ($c) => $c['id'] === $mejor['id'])->values();
                    $asignados[] = sprintf('%s ← «%s» (id %d, %d %%%s) · %s',
                        $nombre, $mejor['nombre'], $mejor['id'], round($mejor['parecido']), $mejor['apellido'] ? ', mismo apellido' : '', $equipo->nombre);
                    continue;
                }

                $pistas = $candidatos->take(3)->map(fn ($c) => sprintf('%s %d %%', $c['nombre'], round($c['parecido'])))->implode(', ');
                $motivo = match (true) {
                    ! $mejor => 'en su equipo de football-data no queda nadie libre',
                    $vale && $empate => 'dos candidatos casi igual de parecidos',
                    default => 'ningún candidato se parece lo suficiente',
                };
                $sinResolver[] = "{$nombre} · {$equipo->nombre} — {$motivo}".($pistas ? ". Candidatos: {$pistas}" : '');
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);

        $this->info(($enSeco ? 'Se asignarían: ' : 'Asignados: ').count($asignados));
        foreach ($asignados as $linea) {
            $this->line("  {$linea}");
        }

        if ($sinResolver) {
            $this->newLine();
            $this->warn('=== SIN RESOLVER ('.count($sinResolver).') — resuélvelos en Admin → Comparar plantillas ===');
            foreach ($sinResolver as $linea) {
                $this->line("  {$linea}");
            }
        }

        if ($fallos) {
            $this->newLine();
            $this->error('=== EQUIPOS QUE NO SE PUDIERON CONSULTAR ('.count($fallos).') ===');
            foreach ($fallos as $linea) {
                $this->line("  {$linea}");
            }
        }

        return self::SUCCESS;
    }
}
