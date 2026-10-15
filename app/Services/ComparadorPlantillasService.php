<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Comparar plantillas (Admin → Comparar plantillas): las plantillas oficiales
 * de football-data contra las nuestras, usando el identificador de la API.
 *
 * Se consulta EQUIPO A EQUIPO (football-data deja ~10 consultas por minuto) y
 * cada plantilla se guarda unos días, así el resultado se puede ir resolviendo
 * con calma. Comparar solo LEE; lo único que cambia algo son las acciones de
 * abajo, y solo cuando el admin las pulsa.
 *
 * Es la versión con pantalla del comando liga:comparar-plantillas (que sigue
 * existiendo), más dos listas nuevas: identificadores que no cuadran con el
 * nombre (para revisar lo que puso "Completar identificadores") y jugadores
 * sin identificador con sus candidatos en la API.
 */
class ComparadorPlantillasService
{
    /** Segundos mínimos entre dos consultas a football-data. Los tests lo ponen a 0. */
    public static float $espera = 6.5;

    private const DIAS_GUARDADO = 7;

    /** Por debajo de este parecido (y sin ningún apellido en común) un identificador es dudoso. */
    private const PARECIDO_MINIMO = 50;

    // ------------------------------------------------------------------
    // Consultar football-data
    // ------------------------------------------------------------------

    public function consultarEquipo(Equipo $equipo): array
    {
        if (! $equipo->id_equipo_api) {
            throw new RuntimeException("{$equipo->nombre} no tiene identificador de football-data.");
        }

        $this->esperarTurno();

        $respuesta = Http::withHeaders(['X-Auth-Token' => config('services.football_data.token')])
            ->timeout(20)
            ->get("https://api.football-data.org/v4/teams/{$equipo->id_equipo_api}");

        Cache::put('comparador:ultima_consulta', microtime(true), 60);

        if ($respuesta->status() === 429) {
            throw new RuntimeException('football-data pide esperar un poco (demasiadas consultas). Vuelve a intentarlo en un minuto.');
        }
        if (! $respuesta->successful()) {
            throw new RuntimeException("football-data respondió {$respuesta->status()} con {$equipo->nombre}.");
        }

        $plantilla = collect($respuesta->json('squad', []))
            ->map(fn ($j) => [
                'id' => (int) $j['id'],
                'nombre' => (string) ($j['name'] ?? '?'),
                'posicion' => $j['position'] ?? null,
                'fecha_nacimiento' => $j['dateOfBirth'] ?? null,
                'nacionalidad' => $j['nationality'] ?? null,
            ])
            ->values()
            ->all();

        $guardado = ['jugadores' => $plantilla, 'consultado_en' => now()->toIso8601String()];
        Cache::put($this->clave($equipo->id), $guardado, now()->addDays(self::DIAS_GUARDADO));

        return ['equipo' => $equipo->nombre, 'jugadores' => count($plantilla)];
    }

    /**
     * La plantilla de un equipo en football-data: la guardada si hay (de los
     * últimos días), y si no, la consulta. La usan también los comandos.
     *
     * @return array<int,array{id:int,nombre:string,posicion:?string,fecha_nacimiento:?string,nacionalidad:?string}>
     */
    public function plantilla(Equipo $equipo, bool $refrescar = false): array
    {
        if ($refrescar || ! Cache::has($this->clave($equipo->id))) {
            $this->consultarEquipo($equipo);
        }

        return Cache::get($this->clave($equipo->id))['jugadores'] ?? [];
    }

    private function esperarTurno(): void
    {
        $ultima = (float) Cache::get('comparador:ultima_consulta', 0);
        $falta = static::$espera - (microtime(true) - $ultima);
        if ($falta > 0) {
            usleep((int) (min($falta, static::$espera) * 1_000_000));
        }
    }

    private function clave(int $idEquipo): string
    {
        return "comparador:plantilla:{$idEquipo}";
    }

    // ------------------------------------------------------------------
    // El resultado
    // ------------------------------------------------------------------

    public function resultado(): array
    {
        $equipos = Equipo::whereNotNull('id_equipo_api')->orderBy('nombre')->get();
        $idTemporada = $this->temporadaActual();

        $plantillasApi = [];   // id_equipo => [jugadores]
        $estado = [];
        foreach ($equipos as $equipo) {
            $guardado = Cache::get($this->clave($equipo->id));
            $estado[] = [
                'id' => $equipo->id,
                'nombre' => $equipo->nombre_corto ?: $equipo->nombre,
                'consultado_en' => $guardado['consultado_en'] ?? null,
            ];
            if ($guardado) {
                $plantillasApi[$equipo->id] = collect($guardado['jugadores']);
            }
        }

        $todos = count($plantillasApi) === $equipos->count() && $equipos->isNotEmpty();
        $porId = $equipos->keyBy('id');
        $nombreEquipo = fn ($id) => $porId[$id]->nombre_corto ?: ($porId[$id]->nombre ?? '?');

        // Dónde está cada identificador de la API ahora mismo.
        $equipoDeIdApi = [];
        $datosDeIdApi = [];
        foreach ($plantillasApi as $idEquipo => $plantilla) {
            foreach ($plantilla as $j) {
                $equipoDeIdApi[$j['id']] = $idEquipo;
                $datosDeIdApi[$j['id']] = $j;
            }
        }

        // Nuestras fichas activas de esta temporada, en equipos con id de la API.
        $fichas = PlantillaTemporada::with('jugador')
            ->where('id_temporada', $idTemporada)
            ->whereNull('fecha_salida')
            ->whereIn('id_equipo', $equipos->pluck('id'))
            ->whereHas('jugador', fn ($q) => $q->whereNull('dado_de_baja_en'))
            ->get();

        $idsApiConocidos = Jugador::whereNotNull('id_externo_api')->pluck('id', 'id_externo_api');
        $confirmados = Cache::get('comparador:confirmados', []);

        $sinCambios = 0;
        $traspasos = [];
        $bajas = [];
        $dudosos = [];
        $sinId = [];

        foreach ($fichas as $ficha) {
            $jugador = $ficha->jugador;
            if (! isset($plantillasApi[$ficha->id_equipo])) {
                continue; // su equipo aún no se ha consultado
            }

            $base = [
                'id_jugador' => $jugador->id,
                'jugador' => $this->nombreLocal($jugador),
                'id_equipo' => $ficha->id_equipo,
                'equipo' => $nombreEquipo($ficha->id_equipo),
                'dorsal' => $ficha->dorsal,
            ];

            if (! $jugador->id_externo_api) {
                $sinId[] = $base + ['candidatos' => $this->candidatosApi($jugador, $plantillasApi[$ficha->id_equipo], $idsApiConocidos)];
                continue;
            }

            $idApi = (int) $jugador->id_externo_api;
            $dondeEsta = $equipoDeIdApi[$idApi] ?? null;

            if ($dondeEsta === $ficha->id_equipo) {
                $parecido = $this->parecido($jugador, $datosDeIdApi[$idApi]['nombre']);
                $confirmado = ($confirmados[$jugador->id] ?? null) === $idApi;
                if (! $confirmado && $parecido < self::PARECIDO_MINIMO && ! $this->compartenApellido($jugador, $datosDeIdApi[$idApi]['nombre'])) {
                    $dudosos[] = $base + ['id_api' => $idApi, 'nombre_api' => $datosDeIdApi[$idApi]['nombre'], 'parecido' => (int) round($parecido)];
                } else {
                    $sinCambios++;
                }
                continue;
            }

            if (! $todos) {
                continue; // sin todas las plantillas no se puede saber si se fue o a dónde
            }

            if ($dondeEsta) {
                $traspasos[] = $base + ['id_api' => $idApi, 'id_equipo_destino' => $dondeEsta, 'equipo_destino' => $nombreEquipo($dondeEsta)];
            } else {
                $bajas[] = $base + ['id_api' => $idApi];
            }
        }

        // Altas: en la API y con un identificador que no tiene nadie aquí.
        $sinIdentificador = Jugador::whereNull('id_externo_api')->whereNull('dado_de_baja_en')->get();
        $fichasPorEquipo = $fichas->groupBy('id_equipo')->map(fn ($f) => $f->pluck('id_jugador')->flip());
        $altas = [];
        foreach ($plantillasApi as $idEquipo => $plantilla) {
            foreach ($plantilla as $j) {
                if ($idsApiConocidos->has($j['id'])) {
                    continue;
                }
                $altas[] = [
                    'id_api' => $j['id'],
                    'nombre_api' => $j['nombre'],
                    'posicion_api' => $j['posicion'],
                    'posicion' => $this->posicionDe($j['posicion']),
                    'fecha_nacimiento' => $j['fecha_nacimiento'],
                    'nacionalidad' => $j['nacionalidad'],
                    'id_equipo' => $idEquipo,
                    'equipo' => $nombreEquipo($idEquipo),
                    'candidatos' => $this->candidatosLocales($j['nombre'], $sinIdentificador, $fichasPorEquipo->get($idEquipo, collect())),
                ];
            }
        }

        usort($altas, fn ($a, $b) => [$a['equipo'], $a['nombre_api']] <=> [$b['equipo'], $b['nombre_api']]);
        foreach ([&$traspasos, &$bajas, &$dudosos, &$sinId] as &$lista) {
            usort($lista, fn ($a, $b) => [$a['equipo'], $a['jugador']] <=> [$b['equipo'], $b['jugador']]);
        }
        unset($lista);

        return [
            'equipos' => $estado,
            'consultados' => count($plantillasApi),
            'total' => $equipos->count(),
            'todos' => $todos,
            'sin_cambios' => $sinCambios,
            'traspasos' => $traspasos,
            'bajas' => $bajas,
            'altas' => $altas,
            'dudosos' => $dudosos,
            'sin_id' => $sinId,
            'posiciones' => CambiosPlantillaService::POSICIONES,
        ];
    }

    // ------------------------------------------------------------------
    // Acciones (solo cuando el admin las pulsa)
    // ------------------------------------------------------------------

    /** "Es este jugador": le pone el identificador de la API. */
    public function vincular(Jugador $jugador, int $idApi): string
    {
        $otro = Jugador::where('id_externo_api', $idApi)->where('id', '!=', $jugador->id)->first();
        if ($otro) {
            throw new RuntimeException("Ese identificador ya lo tiene {$this->nombreLocal($otro)} (id {$otro->id}). Quítaselo primero si estaba mal.");
        }

        $jugador->update(['id_externo_api' => $idApi]);
        Log::info('Comparador: identificador vinculado', ['jugador' => $jugador->id, 'id_api' => $idApi]);

        return "{$this->nombreLocal($jugador)} queda enlazado con el {$idApi} de football-data.";
    }

    /** "Está bien": un identificador que parecía dudoso, revisado y correcto. Deja de salir. */
    public function confirmarIdentificador(Jugador $jugador): string
    {
        $confirmados = Cache::get('comparador:confirmados', []);
        $confirmados[$jugador->id] = (int) $jugador->id_externo_api;
        Cache::forever('comparador:confirmados', $confirmados);

        return "Anotado: el identificador de {$this->nombreLocal($jugador)} está bien.";
    }

    public function quitarIdentificador(Jugador $jugador): string
    {
        $antes = $jugador->id_externo_api;
        $jugador->update(['id_externo_api' => null]);
        Log::info('Comparador: identificador quitado', ['jugador' => $jugador->id, 'id_api' => $antes]);

        return "Quitado el identificador {$antes} a {$this->nombreLocal($jugador)}. Volverá a salir como «sin identificador» para enlazarlo bien.";
    }

    /** Alta de un jugador nuevo con los datos de football-data y su ficha en el equipo. */
    public function darDeAlta(int $idApi, int $idEquipo, string $posicion, ?int $dorsal, string $fecha): string
    {
        if (Jugador::where('id_externo_api', $idApi)->exists()) {
            throw new RuntimeException('Ese jugador ya está dado de alta.');
        }

        $datos = collect(Cache::get($this->clave($idEquipo))['jugadores'] ?? [])->firstWhere('id', $idApi);
        if (! $datos) {
            throw new RuntimeException('No tengo los datos de ese jugador: vuelve a consultar su equipo.');
        }

        $idTemporada = $this->temporadaActual();
        if ($dorsal !== null) {
            $this->exigirDorsalLibre($idEquipo, $idTemporada, $dorsal);
        }

        [$nombre, $apellidos] = $this->partirNombre($datos['nombre']);

        $jugador = DB::transaction(function () use ($nombre, $apellidos, $posicion, $datos, $idApi, $idEquipo, $idTemporada, $dorsal, $fecha) {
            $jugador = Jugador::create([
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'posicion' => $posicion,
                'nacionalidad' => $datos['nacionalidad'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'id_externo_api' => $idApi,
            ]);

            PlantillaTemporada::create([
                'id_jugador' => $jugador->id,
                'id_equipo' => $idEquipo,
                'id_temporada' => $idTemporada,
                'dorsal' => $dorsal,
                'fecha_incorporacion' => $fecha,
                'fecha_salida' => null,
            ]);

            return $jugador;
        });

        Log::info('Comparador: alta', ['jugador' => $jugador->id, 'id_api' => $idApi, 'equipo' => $idEquipo]);

        return "{$this->nombreLocal($jugador)} dado de alta. Si quieres foto o nombre de camiseta, ponlos desde su ficha.";
    }

    /** Cierra su ficha actual el día antes y abre una nueva en el equipo de destino. */
    public function traspasar(Jugador $jugador, int $idEquipoDestino, ?int $dorsal, string $fecha): string
    {
        $idTemporada = $this->temporadaActual();
        $dia = Carbon::parse($fecha)->startOfDay();

        if (PlantillaTemporada::where('id_jugador', $jugador->id)->where('id_equipo', $idEquipoDestino)
            ->where('id_temporada', $idTemporada)->whereNull('fecha_salida')->exists()) {
            throw new RuntimeException('Ya tiene una ficha abierta en ese equipo.');
        }
        if ($dorsal !== null) {
            $this->exigirDorsalLibre($idEquipoDestino, $idTemporada, $dorsal);
        }

        DB::transaction(function () use ($jugador, $idEquipoDestino, $idTemporada, $dorsal, $dia) {
            PlantillaTemporada::where('id_jugador', $jugador->id)
                ->where('id_temporada', $idTemporada)
                ->whereNull('fecha_salida')
                ->update(['fecha_salida' => $dia->copy()->subDay()->toDateString()]);

            PlantillaTemporada::create([
                'id_jugador' => $jugador->id,
                'id_equipo' => $idEquipoDestino,
                'id_temporada' => $idTemporada,
                'dorsal' => $dorsal,
                'fecha_incorporacion' => $dia->toDateString(),
                'fecha_salida' => null,
            ]);
        });

        $destino = Equipo::find($idEquipoDestino);
        Log::info('Comparador: traspaso', ['jugador' => $jugador->id, 'destino' => $idEquipoDestino, 'fecha' => $dia->toDateString()]);

        return "{$this->nombreLocal($jugador)} pasa a {$destino?->nombre} desde el {$dia->format('d/m/Y')}.";
    }

    /** Ya no está en LaLiga: cierra su ficha y, si se pide, lo da de baja. */
    public function salida(Jugador $jugador, string $fecha, bool $darDeBaja): string
    {
        $dia = Carbon::parse($fecha)->toDateString();

        DB::transaction(function () use ($jugador, $dia, $darDeBaja) {
            PlantillaTemporada::where('id_jugador', $jugador->id)
                ->where('id_temporada', $this->temporadaActual())
                ->whereNull('fecha_salida')
                ->update(['fecha_salida' => $dia]);

            if ($darDeBaja) {
                $jugador->update(['dado_de_baja_en' => $dia]);
            }
        });

        Log::info('Comparador: salida', ['jugador' => $jugador->id, 'fecha' => $dia, 'baja' => $darDeBaja]);

        return $darDeBaja
            ? "{$this->nombreLocal($jugador)}: ficha cerrada y dado de baja."
            : "{$this->nombreLocal($jugador)}: ficha cerrada el {$dia}.";
    }

    // ------------------------------------------------------------------

    public function temporadaActual(): int
    {
        return (int) Temporada::orderByDesc('fecha_inicio')->value('id');
    }

    private function exigirDorsalLibre(int $idEquipo, int $idTemporada, int $dorsal): void
    {
        $ocupante = PlantillaTemporada::with('jugador')
            ->where('id_equipo', $idEquipo)->where('id_temporada', $idTemporada)
            ->where('dorsal', $dorsal)->whereNull('fecha_salida')->first();

        if ($ocupante) {
            throw new RuntimeException("El dorsal {$dorsal} ya lo lleva {$this->nombreLocal($ocupante->jugador)} en ese equipo.");
        }
    }

    public function nombreLocal(?Jugador $jugador): string
    {
        if (! $jugador) {
            return 'Jugador';
        }
        $completo = trim("{$jugador->nombre} {$jugador->apellidos}");

        return $jugador->nombre_camiseta && $jugador->nombre_camiseta !== $completo
            ? "{$completo} ({$jugador->nombre_camiseta})"
            : $completo;
    }

    private function normalizar(?string $texto): string
    {
        return Str::of((string) $texto)->ascii()->lower()->replaceMatches('/[^a-z ]/', ' ')->squish()->toString();
    }

    /** Lo mejor de comparar el nombre de la API con nombre+apellidos, camiseta y apellidos. */
    public function parecido(Jugador $jugador, string $nombreApi): float
    {
        $api = $this->normalizar($nombreApi);
        $mejor = 0.0;
        foreach ([trim("{$jugador->nombre} {$jugador->apellidos}"), $jugador->nombre_camiseta, $jugador->apellidos] as $local) {
            $local = $this->normalizar($local);
            if ($local === '') {
                continue;
            }
            similar_text($local, $api, $porcentaje);
            $mejor = max($mejor, $porcentaje);
        }

        return $mejor;
    }

    /** ¿Alguna palabra de 4+ letras del nombre de la API está en su nombre completo o de camiseta? */
    public function compartenApellido(Jugador $jugador, string $nombreApi): bool
    {
        $local = ' '.$this->normalizar("{$jugador->nombre} {$jugador->apellidos} {$jugador->nombre_camiseta}").' ';
        foreach (explode(' ', $this->normalizar($nombreApi)) as $palabra) {
            if (strlen($palabra) >= 4 && str_contains($local, " {$palabra} ")) {
                return true;
            }
        }

        return false;
    }

    /** Para un jugador nuestro sin identificador: los de su equipo en la API que nadie tiene, por parecido. */
    private function candidatosApi(Jugador $jugador, Collection $plantilla, Collection $idsApiConocidos): array
    {
        return $plantilla
            ->reject(fn ($j) => $idsApiConocidos->has($j['id']))
            ->map(fn ($j) => ['id_api' => $j['id'], 'nombre' => $j['nombre'], 'parecido' => (int) round($this->parecido($jugador, $j['nombre']))])
            ->sortByDesc('parecido')
            ->take(5)
            ->values()
            ->all();
    }

    /** Para un alta: jugadores nuestros sin identificador que podrían ser él (primero los de su equipo). */
    private function candidatosLocales(string $nombreApi, Collection $sinIdentificador, Collection $delEquipo): array
    {
        return $sinIdentificador
            ->map(fn (Jugador $j) => [
                'id_jugador' => $j->id,
                'nombre' => $this->nombreLocal($j),
                'parecido' => (int) round($this->parecido($j, $nombreApi)),
                'de_su_equipo' => $delEquipo->has($j->id),
            ])
            ->filter(fn ($c) => $c['de_su_equipo'] || $c['parecido'] >= 60)
            ->sortBy([['de_su_equipo', 'desc'], ['parecido', 'desc']])
            ->take(6)
            ->values()
            ->all();
    }

    public function posicionDe(?string $posicionApi): ?string
    {
        $p = Str::lower((string) $posicionApi);

        return match (true) {
            $p === '' => null,
            str_contains($p, 'goal') => 'Portero',
            str_contains($p, 'back') || str_contains($p, 'defen') => 'Defensa',
            str_contains($p, 'midfield') => 'Centrocampista',
            str_contains($p, 'forward') || str_contains($p, 'offen') || str_contains($p, 'wing') || str_contains($p, 'striker') => 'Delantero',
            default => null,
        };
    }

    /** "Robert Lewandowski" → ["Robert", "Lewandowski"]; un solo nombre va entero a "nombre". */
    public function partirNombre(string $nombreApi): array
    {
        $partes = preg_split('/\s+/', trim($nombreApi), 2);

        return [$partes[0], $partes[1] ?? null];
    }
}
