<?php

namespace App\Services;

use App\Models\AliasJugadorLaliga;
use App\Models\CalendarioPartido;
use App\Models\CambioPlantilla;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\PlantillaTemporada;
use App\Models\Temporada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * La cola de cambios de plantilla por revisar (/admin/cambios-plantilla).
 *
 * Tiene dos mitades:
 *  - lo que APUNTA el importador de LaLiga cuando no puede emparejar a alguien
 *    o ve un dorsal distinto al de plantilla_temporada;
 *  - lo que RESUELVE el admin con un clic: dar de alta, decir "es este
 *    jugador", aplicar el dorsal o ignorar.
 *
 * Nada se cambia solo: el importador únicamente apunta. Los errores que el
 * admin debe leer se lanzan como \DomainException.
 */
class CambiosPlantillaService
{
    public const POSICIONES = ['Portero', 'Defensa', 'Centrocampista', 'Delantero'];

    /** En LaLiga los dorsales 1-25 son de primera plantilla; del 26 en adelante, ficha de filial. */
    public const DORSAL_CANTERANO = 26;

    /** Tamaños que se piden al servidor de fotos de LaLiga, del mejor al que seguro existe. */
    private const TAMANOS_FOTO = ['512x512', '256x256', '128x128', '64x64'];

    // ------------------------------------------------------------------
    // Lo que usa el importador
    // ------------------------------------------------------------------

    /** @return array<string,int> nombre de LaLiga => id_jugador, para un equipo */
    public function aliasDeEquipo(int $idEquipo): array
    {
        return AliasJugadorLaliga::where('id_equipo', $idEquipo)->pluck('id_jugador', 'clave')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<string,string> "tipo|nombre de LaLiga" => estado, para un equipo */
    public function estadosDeEquipo(int $idEquipo): array
    {
        $estados = [];
        foreach (CambioPlantilla::where('id_equipo', $idEquipo)->get(['tipo', 'clave', 'estado']) as $cambio) {
            $estados["{$cambio->tipo}|{$cambio->clave}"] = $cambio->estado;
        }

        return $estados;
    }

    /**
     * Apunta (o actualiza) a alguien de una alineación que no se pudo emparejar.
     *
     * @return bool false si el admin lo tiene marcado como "ignorar": no hay que avisar de él.
     */
    public function anotarSinEmparejar(int $idEquipo, ?int $idTemporada, int $idPartido, array $person, string $clave, ?int $dorsal, ?string $foto, string $pista, ?int $idSugerido, string $motivo): bool
    {
        $cambio = CambioPlantilla::firstOrNew(['id_equipo' => $idEquipo, 'clave' => $clave, 'tipo' => 'jugador']);

        if ($cambio->exists && $cambio->estado === 'ignorado') {
            return false;
        }

        $cambio->fill([
            // Si estaba resuelto y vuelve a no emparejar, es que algo no cuadra: se reabre.
            'estado' => 'pendiente',
            'pista' => $pista,
            'id_temporada' => $idTemporada,
            'nombre_laliga' => $person['name'] ?? $person['nickname'] ?? $clave,
            'apodo_laliga' => $person['nickname'] ?? null,
            'nombre_pila_laliga' => $person['firstname'] ?? null,
            'apellidos_laliga' => $person['lastname'] ?? null,
            'dorsal' => $dorsal ?? $cambio->dorsal,
            'foto_laliga' => $foto ?? $cambio->foto_laliga,
            'id_jugador_sugerido' => $idSugerido,
            'motivo' => mb_substr($motivo, 0, 600),
            'partidos' => $this->conPartido($cambio->partidos, $idPartido),
            'partidos_por_reimportar' => null,
            'id_jugador_resuelto' => null,
            'resuelto_como' => null,
            'resuelto_en' => null,
        ])->save();

        return true;
    }

    /** Propone cambiar el dorsal de un jugador ya emparejado, porque LaLiga trae otro. */
    public function anotarDorsal(int $idEquipo, ?int $idTemporada, int $idPartido, array $person, string $clave, int $dorsalLaliga, int $idJugador, ?int $dorsalActual): void
    {
        $cambio = CambioPlantilla::firstOrNew(['id_equipo' => $idEquipo, 'clave' => $clave, 'tipo' => 'dorsal']);

        // Ignorado para ESTE dorsal: no se vuelve a proponer. Si LaLiga cambia a otro número, sí.
        if ($cambio->exists && $cambio->estado === 'ignorado' && (int) $cambio->dorsal === $dorsalLaliga) {
            return;
        }

        $actual = $dorsalActual === null ? 'no tiene dorsal' : "lleva el {$dorsalActual}";

        $cambio->fill([
            'estado' => 'pendiente',
            'pista' => null,
            'id_temporada' => $idTemporada,
            'nombre_laliga' => $person['name'] ?? $person['nickname'] ?? $clave,
            'apodo_laliga' => $person['nickname'] ?? null,
            'nombre_pila_laliga' => $person['firstname'] ?? null,
            'apellidos_laliga' => $person['lastname'] ?? null,
            'dorsal' => $dorsalLaliga,
            'id_jugador_sugerido' => $idJugador,
            'motivo' => "En tu plantilla {$actual}; LaLiga lo trae con el {$dorsalLaliga}.",
            'partidos' => $this->conPartido($cambio->partidos, $idPartido),
            'partidos_por_reimportar' => null,
            'id_jugador_resuelto' => null,
            'resuelto_como' => null,
            'resuelto_en' => null,
        ])->save();
    }

    /**
     * Alguien que estaba pendiente ya empareja (se arregló por otro lado, p. ej.
     * dándolo de alta desde la pantalla de jugadores). Se cierra, y los partidos
     * anteriores donde no emparejaba quedan por actualizar.
     */
    public function cerrarPorqueYaEmpareja(int $idEquipo, string $clave, int $idJugador, int $idPartidoActual): void
    {
        $cambio = CambioPlantilla::where(['id_equipo' => $idEquipo, 'clave' => $clave, 'tipo' => 'jugador', 'estado' => 'pendiente'])->first();
        if (! $cambio) return;

        $restantes = array_values(array_diff($cambio->partidos ?? [], [$idPartidoActual]));

        $cambio->update([
            'estado' => 'resuelto',
            'resuelto_como' => 'automatico',
            'id_jugador_resuelto' => $idJugador,
            'resuelto_en' => now(),
            'partidos_por_reimportar' => $restantes ?: null,
        ]);
    }

    public function cerrarDorsalPorqueYaCoincide(int $idEquipo, string $clave, int $idJugador): void
    {
        CambioPlantilla::where(['id_equipo' => $idEquipo, 'clave' => $clave, 'tipo' => 'dorsal', 'estado' => 'pendiente'])
            ->update(['estado' => 'resuelto', 'resuelto_como' => 'automatico', 'id_jugador_resuelto' => $idJugador, 'resuelto_en' => now()]);
    }

    // ------------------------------------------------------------------
    // Lo que resuelve el admin
    // ------------------------------------------------------------------

    /**
     * Crea el jugador y su ficha en la plantilla del equipo con los datos ya
     * revisados por el admin, y deja recordado su nombre de LaLiga.
     *
     * @return array{jugador: Jugador, aviso: ?string}
     */
    public function darDeAlta(CambioPlantilla $cambio, array $datos): array
    {
        $this->exigirPendiente($cambio, 'jugador');

        $idTemporada = $this->temporadaDe($cambio);
        $dorsal = isset($datos['dorsal']) && $datos['dorsal'] !== '' ? (int) $datos['dorsal'] : null;

        if ($dorsal !== null) {
            $this->exigirDorsalLibre($cambio->id_equipo, $idTemporada, $dorsal);
        }

        $jugador = DB::transaction(function () use ($cambio, $datos, $idTemporada, $dorsal) {
            $jugador = Jugador::create([
                'nombre' => $datos['nombre'],
                'apellidos' => $datos['apellidos'] ?? null,
                'nombre_camiseta' => $datos['nombre_camiseta'] ?? null,
                'posicion' => $datos['posicion'],
                'pie' => $datos['pie'] ?? null,
                'nacionalidad' => $datos['nacionalidad'] ?? null,
                'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
                'lugar_nacimiento' => $datos['lugar_nacimiento'] ?? null,
                'altura' => $datos['altura'] ?? null,
                'fecha_fin_contrato' => $datos['fecha_fin_contrato'] ?? null,
                'club_anterior' => $datos['club_anterior'] ?? null,
            ]);

            PlantillaTemporada::create([
                'id_jugador' => $jugador->id,
                'id_equipo' => $cambio->id_equipo,
                'id_temporada' => $idTemporada,
                'dorsal' => $dorsal,
                // Sin fecha = "desde siempre": así también empareja en los partidos ya jugados.
                'fecha_incorporacion' => $datos['fecha_incorporacion'] ?? null,
                'fecha_salida' => null,
            ]);

            $this->recordarAlias($cambio, $jugador->id);
            $this->marcarResuelto($cambio, 'alta', $jugador->id, reimportar: true);

            return $jugador;
        });

        // La foto va aparte: si LaLiga no responde, el alta ya está hecha igualmente.
        $aviso = null;
        if (! empty($datos['usar_foto_laliga']) && $cambio->foto_laliga) {
            $url = $this->descargarFoto($cambio->foto_laliga, $jugador->id);
            if ($url) {
                $jugador->update(['foto_url' => $url]);
            } else {
                $aviso = 'El jugador se ha creado, pero no se pudo descargar la foto de LaLiga. Puedes subirla desde su ficha.';
            }
        }

        return ['jugador' => $jugador, 'aviso' => $aviso];
    }

    /**
     * "Ese nombre de LaLiga es este jugador que ya tengo". Queda recordado para siempre.
     *
     * Además deja su ficha de plantilla cubriendo los partidos donde LaLiga lo
     * alineó. Sin eso la equivalencia no servía de nada: el importador solo
     * empareja con quien figura en la plantilla el DÍA del partido, así que un
     * jugador con fecha de incorporación posterior (o sin ficha en el equipo)
     * volvía a salir como pendiente en cada reimportación.
     *
     * @return ?string lo que se ha tocado en su ficha, para contárselo al admin
     */
    public function asignar(CambioPlantilla $cambio, Jugador $jugador, bool $actualizarDorsal): ?string
    {
        $this->exigirPendiente($cambio, 'jugador');

        $idTemporada = $this->temporadaDe($cambio);
        [$desde, $hasta] = $this->fechasDeLosPartidos($cambio);

        $equipo = Equipo::find($cambio->id_equipo);
        $nombreEquipo = $equipo->nombre_corto ?? $equipo->nombre ?? 'ese equipo';
        $nombreJugador = trim("{$jugador->nombre} {$jugador->apellidos}");

        $ficha = PlantillaTemporada::where('id_jugador', $jugador->id)
            ->where('id_equipo', $cambio->id_equipo)
            ->where('id_temporada', $idTemporada)
            ->orderByRaw('fecha_salida is null desc')
            ->first();

        if (! $ficha) {
            // Si está en activo en OTRO equipo, esto es un traspaso: hay que decidir fechas a mano.
            $enOtroEquipo = PlantillaTemporada::with('equipo')
                ->where('id_jugador', $jugador->id)
                ->where('id_temporada', $idTemporada)
                ->whereNull('fecha_salida')
                ->first();

            if ($enOtroEquipo) {
                $otro = $enOtroEquipo->equipo->nombre_corto ?? $enOtroEquipo->equipo->nombre ?? 'otro equipo';
                throw new \DomainException("{$nombreJugador} no está en la plantilla de {$nombreEquipo}: figura en la de {$otro}. Si ha cambiado de equipo, fíchalo desde su ficha de jugador (con la fecha real del fichaje) y vuelve aquí.");
            }
        }

        $notas = [];

        DB::transaction(function () use ($cambio, $jugador, &$ficha, $actualizarDorsal, $idTemporada, $desde, $hasta, $nombreEquipo, &$notas) {
            if (! $ficha) {
                $ficha = PlantillaTemporada::create([
                    'id_jugador' => $jugador->id,
                    'id_equipo' => $cambio->id_equipo,
                    'id_temporada' => $idTemporada,
                    'dorsal' => null,
                    'fecha_incorporacion' => $desde,
                    'fecha_salida' => null,
                ]);
                $notas[] = "no tenía equipo: se le ha puesto en la plantilla de {$nombreEquipo}";
            } else {
                $ajuste = [];
                $incorporacion = $ficha->fecha_incorporacion ? substr((string) $ficha->fecha_incorporacion, 0, 10) : null;
                $salida = $ficha->fecha_salida ? substr((string) $ficha->fecha_salida, 0, 10) : null;

                if ($desde && $incorporacion && $incorporacion > $desde) {
                    $ajuste['fecha_incorporacion'] = $desde;
                    $notas[] = "su fecha de incorporación era el {$incorporacion}, posterior a un partido que ya jugó: se ha adelantado al {$desde}";
                }
                if ($hasta && $salida && $salida < $hasta) {
                    $ajuste['fecha_salida'] = $hasta;
                    $notas[] = "su fecha de salida era el {$salida}, anterior a un partido que jugó: se ha retrasado al {$hasta}";
                }
                if ($ajuste) {
                    $ficha->update($ajuste);
                }
            }

            if ($actualizarDorsal && $cambio->dorsal !== null && (int) $ficha->dorsal !== (int) $cambio->dorsal) {
                $this->exigirDorsalLibre($cambio->id_equipo, $idTemporada, (int) $cambio->dorsal, $ficha->id);
                $ficha->update(['dorsal' => $cambio->dorsal]);
            }

            $this->recordarAlias($cambio, $jugador->id);
            $this->marcarResuelto($cambio, 'asignado', $jugador->id, reimportar: true);
        });

        return $notas ? ucfirst(implode('; ', $notas)).'.' : null;
    }

    /**
     * Primer y último día en que LaLiga alineó a esta persona, según los partidos
     * apuntados en el cambio.
     *
     * @return array{0:?string,1:?string} fechas 'Y-m-d'
     */
    private function fechasDeLosPartidos(CambioPlantilla $cambio): array
    {
        $fechas = CalendarioPartido::whereIn('id', $cambio->partidos ?? [])
            ->whereNotNull('horario_estimado')
            ->get(['id', 'horario_estimado'])
            ->map(fn ($p) => $p->horario_estimado->toDateString())
            ->sort()
            ->values();

        return [$fechas->first(), $fechas->last()];
    }

    /** Pone en la plantilla el dorsal que trae LaLiga. */
    public function aplicarDorsal(CambioPlantilla $cambio): void
    {
        $this->exigirPendiente($cambio, 'dorsal');

        $idTemporada = $this->temporadaDe($cambio);

        $ficha = PlantillaTemporada::where('id_jugador', $cambio->id_jugador_sugerido)
            ->where('id_equipo', $cambio->id_equipo)
            ->where('id_temporada', $idTemporada)
            ->whereNull('fecha_salida')
            ->first();

        if (! $ficha) {
            throw new \DomainException('Ese jugador ya no tiene ficha activa en ese equipo.');
        }

        $this->exigirDorsalLibre($cambio->id_equipo, $idTemporada, (int) $cambio->dorsal, $ficha->id);

        DB::transaction(function () use ($cambio, $ficha) {
            $ficha->update(['dorsal' => $cambio->dorsal]);
            $this->marcarResuelto($cambio, 'dorsal', (int) $cambio->id_jugador_sugerido, reimportar: false);
        });
    }

    public function ignorar(CambioPlantilla $cambio): void
    {
        $cambio->update(['estado' => 'ignorado', 'partidos_por_reimportar' => null, 'resuelto_en' => now()]);
    }

    /** Para lo que el admin ha arreglado por su cuenta (fechas de la ficha, un fichaje...). */
    public function darPorArreglado(CambioPlantilla $cambio): void
    {
        $this->exigirPendiente($cambio, $cambio->tipo);
        $this->marcarResuelto($cambio, 'manual', $cambio->id_jugador_sugerido, reimportar: $cambio->tipo === 'jugador');
    }

    /**
     * A quién se puede asignar: la plantilla del equipo y, si se escribe algo,
     * cualquier jugador cuyo nombre lo contenga.
     *
     * @return array<int,array{id:int,nombre:string,detalle:string}>
     */
    public function candidatos(CambioPlantilla $cambio, ?string $busqueda): array
    {
        $idTemporada = $this->temporadaDe($cambio);
        $busqueda = trim((string) $busqueda);

        $deLaPlantilla = PlantillaTemporada::with('jugador')
            ->where('id_equipo', $cambio->id_equipo)
            ->where('id_temporada', $idTemporada)
            ->whereNull('fecha_salida')
            ->get()
            ->filter(fn ($ficha) => $ficha->jugador !== null)
            ->map(fn ($ficha) => [
                'id' => $ficha->jugador->id,
                'nombre' => trim("{$ficha->jugador->nombre} {$ficha->jugador->apellidos}"),
                'detalle' => $ficha->dorsal !== null ? "dorsal {$ficha->dorsal}" : 'sin dorsal',
            ])
            ->sortBy('nombre')
            ->values();

        if (mb_strlen($busqueda) < 2) {
            return $deLaPlantilla->all();
        }

        $termino = '%'.$busqueda.'%';
        $idsDeLaPlantilla = $deLaPlantilla->pluck('id')->all();

        $otros = Jugador::where(function ($q) use ($termino) {
            $q->where('nombre', 'like', $termino)
                ->orWhere('apellidos', 'like', $termino)
                ->orWhere('nombre_camiseta', 'like', $termino);
        })
            ->whereNotIn('id', $idsDeLaPlantilla)
            ->orderBy('nombre')
            ->limit(20)
            ->get()
            ->map(fn ($jugador) => [
                'id' => $jugador->id,
                'nombre' => trim("{$jugador->nombre} {$jugador->apellidos}"),
                'detalle' => 'no está en esta plantilla',
            ]);

        $coinciden = $deLaPlantilla->filter(fn ($c) => mb_stripos($c['nombre'], $busqueda) !== false)->values();

        return $coinciden->concat($otros)->all();
    }

    // ------------------------------------------------------------------
    // Ayudantes
    // ------------------------------------------------------------------

    private function exigirPendiente(CambioPlantilla $cambio, string $tipo): void
    {
        if ($cambio->tipo !== $tipo || $cambio->estado !== 'pendiente') {
            throw new \DomainException('Este cambio ya no está pendiente. Recarga la página.');
        }
    }

    private function exigirDorsalLibre(int $idEquipo, int $idTemporada, int $dorsal, ?int $exceptoFicha = null): void
    {
        $ocupante = PlantillaTemporada::with('jugador')
            ->where('id_equipo', $idEquipo)
            ->where('id_temporada', $idTemporada)
            ->where('dorsal', $dorsal)
            ->whereNull('fecha_salida')
            ->when($exceptoFicha, fn ($q) => $q->where('id', '!=', $exceptoFicha))
            ->first();

        if ($ocupante) {
            $nombre = $ocupante->jugador ? trim("{$ocupante->jugador->nombre} {$ocupante->jugador->apellidos}") : 'otro jugador';
            throw new \DomainException("El dorsal {$dorsal} ya lo lleva {$nombre} en ese equipo. Cámbiale antes el dorsal a él, o deja este sin dorsal.");
        }
    }

    private function recordarAlias(CambioPlantilla $cambio, int $idJugador): void
    {
        AliasJugadorLaliga::updateOrCreate(
            ['id_equipo' => $cambio->id_equipo, 'clave' => $cambio->clave],
            ['id_jugador' => $idJugador]
        );
    }

    private function marcarResuelto(CambioPlantilla $cambio, string $como, ?int $idJugador, bool $reimportar): void
    {
        $cambio->update([
            'estado' => 'resuelto',
            'resuelto_como' => $como,
            'id_jugador_resuelto' => $idJugador,
            'resuelto_en' => now(),
            'partidos_por_reimportar' => $reimportar && ! empty($cambio->partidos) ? array_values($cambio->partidos) : null,
        ]);
    }

    private function temporadaDe(CambioPlantilla $cambio): int
    {
        return (int) ($cambio->id_temporada ?? Temporada::orderByDesc('fecha_inicio')->value('id'));
    }

    /** @return array<int,int> */
    private function conPartido(?array $partidos, int $idPartido): array
    {
        $partidos = array_map('intval', $partidos ?? []);
        if (! in_array($idPartido, $partidos, true)) {
            $partidos[] = $idPartido;
        }

        return $partidos;
    }

    /**
     * Baja la foto de LaLiga al mismo sitio y con el mismo formato de nombre
     * que las que se suben a mano. LaLiga solo anuncia la de 64x64; se prueban
     * antes tamaños mayores del mismo servidor por si existen.
     */
    private function descargarFoto(string $url, int $idJugador): ?string
    {
        foreach (self::TAMANOS_FOTO as $tamano) {
            $candidata = str_replace('64x64', $tamano, $url);

            try {
                $respuesta = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; PronostiCupBot/1.0; uso personal, no comercial)'])
                    ->timeout(10)
                    ->get($candidata);
            } catch (\Throwable $e) {
                continue;
            }

            $esImagen = str_starts_with((string) $respuesta->header('Content-Type'), 'image/');
            if (! $respuesta->successful() || ! $esImagen || strlen($respuesta->body()) < 500) {
                continue;
            }

            $ruta = "jugadores/{$idJugador}-".uniqid().'.png';
            Storage::disk('public')->put($ruta, $respuesta->body());

            return url(Storage::url($ruta));
        }

        return null;
    }
}
