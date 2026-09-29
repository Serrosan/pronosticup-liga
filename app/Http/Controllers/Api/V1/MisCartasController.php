<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\User;
use App\Notifications\FaltaJugadaContraTi;
use App\Services\MotorEfectosCartas;
use App\Services\MotorFaltas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MisCartasController extends Controller
{
    public function __construct(
        private MotorEfectosCartas $motor,
        private MotorFaltas $motorFaltas,
    ) {}

    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        if ($liga->tipo !== 'ConExtras') {
            return response()->json(['data' => ['activo' => false, 'cartas' => [], 'jugadas' => [], 'historial' => [], 'faltas_recibidas' => [], 'sin_abrir' => 0, 'tope_mano_cartas' => null]]);
        }

        // Solo mostramos en la mano las cartas ya "abiertas" — las que aún no se han
        // revelado no cuentan como visibles todavía, aunque ya sean tuyas de verdad.
        $cartasEnMano = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'en_mano')
            ->whereNotNull('revelada_en')
            ->with('tipoCarta.categoria')
            ->orderByDesc('revelada_en')
            ->get()
            ->map(function ($c) {
                $datos = $c->toArray();
                $datos['requiere_partido'] = $this->motor->requiereEleccionDePartido($c->tipoCarta->codigo_efecto);
                return $datos;
            });

        $sinAbrir = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'en_mano')
            ->whereNull('revelada_en')
            ->count();

        $relacionesResumen = ['tipoCarta.categoria', 'partido.equipoLocal', 'partido.equipoVisitante', 'usuarioObjetivo'];

        $cartasJugadas = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->whereIn('estado', ['jugada', 'pendiente_resolucion'])
            ->with($relacionesResumen)
            ->orderByDesc('jugada_en')
            ->get()
            ->map(fn ($c) => $this->resumenCartaJugada($c, $request->user()->id));

        // Qué pasó con las cartas que ya se resolvieron (las 30 más recientes)
        $historial = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->whereIn('estado', ['resuelta_cumplida', 'resuelta_no_cumplida'])
            ->with($relacionesResumen)
            ->orderByDesc('jugada_en')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn ($c) => $this->resumenCartaJugada($c, $request->user()->id));

        // Faltas que otros te han jugado a ti — para que sepas qué tienes
        // encima, sin tener que descubrirlo solo cuando algo te bloquea.
        $faltasRecibidas = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario_objetivo', $request->user()->id)
            ->where('id_usuario', '!=', $request->user()->id)
            ->where('estado', 'jugada')
            ->with(['tipoCarta.categoria', 'usuario'])
            ->orderByDesc('jugada_en')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'tipo_carta' => $c->tipoCarta,
                'atacante' => $c->tipoCarta->codigo_efecto === 'FAL-COM-INVISIBLE' ? 'Alguien...' : ($c->usuario->nombre_visible ?? $c->usuario->name),
                'jornada_efecto' => $c->jornada_efecto,
                'mensaje_falta' => $c->mensaje_falta,
            ]);

        return response()->json([
            'data' => [
                'activo' => true,
                'tope_mano_cartas' => $liga->tope_mano_cartas ?? 8,
                'cartas' => $cartasEnMano,
                'jugadas' => $cartasJugadas,
                'historial' => $historial,
                'faltas_recibidas' => $faltasRecibidas,
                'sin_abrir' => $sinAbrir,
            ],
        ]);
    }

    /**
     * "Abre" la carta sin revelar más antigua que tengas pendiente y devuelve sus datos
     * completos — el sorteo ya ocurrió al repartirla, esto solo la marca como vista.
     */
    public function abrirSiguiente(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $carta = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'en_mano')
            ->whereNull('revelada_en')
            ->with('tipoCarta.categoria')
            ->orderBy('obtenida_en')
            ->first();

        if (! $carta) {
            return response()->json(['message' => 'No tienes ninguna carta pendiente de abrir.'], 422);
        }

        $carta->update(['revelada_en' => now()]);

        return response()->json(['data' => $carta]);
    }

    /**
     * Para el selector de rivales: qué candidatos quedarían bloqueados y por qué,
     * antes de que el jugador elija — no hace falta intentarlo para enterarse.
     */
    public function rivalesBloqueados(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $proximaJornada = $this->proximaJornadaNumero($liga);

        if (! $proximaJornada) {
            return response()->json(['data' => ['jornada_efecto' => null, 'motivos' => new \stdClass]]);
        }

        $jornadaEfecto = $proximaJornada + 1;
        $totalMiembros = $liga->usuarios()->count();

        $motivos = $this->motorFaltas->motivosBloqueoPorRival($liga->id, $request->user()->id, $jornadaEfecto, $totalMiembros);

        return response()->json(['data' => ['jornada_efecto' => $jornadaEfecto, 'motivos' => (object) $motivos]]);
    }

    public function proximaJornadaJugable(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $proximaJornadaNumero = $this->proximaJornadaNumero($liga);

        if (! $proximaJornadaNumero) {
            return response()->json(['data' => ['jornada' => null, 'bloqueada' => false, 'cierra_en' => null, 'partidos' => []]]);
        }

        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $proximaJornadaNumero)
            ->where('estado', 'Programado')
            ->with(['equipoLocal', 'equipoVisitante'])
            ->orderBy('horario_estimado')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'equipo_local' => $p->equipoLocal->nombre_corto ?? $p->equipoLocal->nombre,
                'equipo_visitante' => $p->equipoVisitante->nombre_corto ?? $p->equipoVisitante->nombre,
                'escudo_local' => $p->equipoLocal->escudo_url,
                'escudo_visitante' => $p->equipoVisitante->escudo_url,
                'horario_estimado' => $p->horario_estimado?->format('d/m H:i'),
            ]);

        return response()->json(['data' => [
            'jornada' => $proximaJornadaNumero,
            // ¿La jornada ya ha empezado? Entonces las Jugadas ya no se pueden jugar sobre ella.
            'bloqueada' => CalendarioPartido::jornadaBloqueada($liga->id_temporada, $proximaJornadaNumero),
            // Cuándo empieza (= cuándo se acaba el plazo para jugar Jugadas en esta jornada)
            'cierra_en' => $this->inicioEfectivoDeJornada($liga, $proximaJornadaNumero)?->toIso8601String(),
            'partidos' => $partidos,
        ]]);
    }

    /**
     * Jugar una carta de Faltas — apunta a un rival (o a ti mismo, en el caso
     * de Escudo) y afecta a la jornada SIGUIENTE a la que está abierta ahora
     * mismo (se juega en la jornada N, el efecto cae en la N+1).
     */
    public function jugarFalta(Request $request, CartaUsuario $cartaUsuario)
    {
        if ($error = $this->verificarPropiaEnMano($cartaUsuario, $request)) {
            return $error;
        }

        $cartaUsuario->load('tipoCarta.categoria');

        if ($cartaUsuario->tipoCarta->categoria->nombre !== 'Faltas') {
            return response()->json(['message' => 'Esta carta no es de tipo Falta.'], 422);
        }

        $liga = $request->user()->ligaActiva;

        $proximaJornada = $this->proximaJornadaNumero($liga);

        if (! $proximaJornada) {
            return response()->json(['message' => 'No hay ninguna jornada disponible ahora mismo.'], 422);
        }

        $yaJugadaEstaJornada = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'jugada')
            ->where('jornada_efecto', $proximaJornada + 1)
            ->whereHas('tipoCarta.categoria', fn ($q) => $q->where('nombre', 'Faltas'))
            ->exists();

        if ($yaJugadaEstaJornada) {
            return response()->json(['message' => 'Solo puedes jugar 1 Falta por jornada.'], 422);
        }

        if ($error = $this->comprobarExpulsion($liga, $request->user(), $proximaJornada, 'Faltas')) {
            return $error;
        }

        $jornadaEfecto = $proximaJornada + 1;
        $esEscudo = $cartaUsuario->tipoCarta->codigo_efecto === 'FAL-PCOM-ESCUDO';

        if ($esEscudo) {
            // El Escudo se protege a sí mismo, no elige rival ni pasa por las
            // reglas anti-abuso (esas solo tienen sentido entre 2 personas).
            $cartaUsuario->update([
                'estado' => 'jugada',
                'id_usuario_objetivo' => $request->user()->id,
                'jornada_efecto' => $jornadaEfecto,
                'jugada_en' => now(),
            ]);

            return response()->json(['message' => "Escudo activado. Te protegerá en la jornada {$jornadaEfecto}."]);
        }

        $validated = $request->validate([
            'id_usuario_objetivo' => ['required', 'exists:users,id', 'different:'.$request->user()->id],
            'mensaje' => ['nullable', 'string', 'max:200'],
        ]);

        $esMiembro = $liga->usuarios()->where('users.id', $validated['id_usuario_objetivo'])->exists();
        if (! $esMiembro) {
            return response()->json(['message' => 'Ese usuario no pertenece a tu liga.'], 422);
        }

        $totalMiembros = $liga->usuarios()->count();

        $problema = $this->motorFaltas->comprobarAntiAbuso($liga->id, $request->user()->id, $validated['id_usuario_objetivo'], $jornadaEfecto, $totalMiembros);
        if ($problema) {
            return response()->json(['message' => $problema], 422);
        }

        $escudoConsumido = $this->motorFaltas->consumirEscudoSiActivo($liga->id, $validated['id_usuario_objetivo'], $jornadaEfecto);
        if ($escudoConsumido) {
            // El Escudo detiene el ataque, pero el intento también le cuesta la carta
            // al atacante — si no, el Escudo solo pararía el primer intento y el
            // atacante podría repetir sin coste hasta colarla.
            $cartaUsuario->update([
                'estado' => 'resuelta_no_cumplida',
                'id_usuario_objetivo' => $validated['id_usuario_objetivo'],
                'jornada_efecto' => $jornadaEfecto,
                'jugada_en' => now(),
                'puntos_generados' => 0,
            ]);

            return response()->json(['message' => 'Ese jugador tenía un Escudo activo esta semana — tu Falta se ha perdido, el Escudo la ha bloqueado.'], 422);
        }

        $cartaUsuario->update([
            'estado' => 'jugada',
            'id_usuario_objetivo' => $validated['id_usuario_objetivo'],
            'jornada_efecto' => $jornadaEfecto,
            'mensaje_falta' => $validated['mensaje'] ?? null,
            'jugada_en' => now(),
        ]);

        $victima = User::find($validated['id_usuario_objetivo']);
        $nombreAtacante = $request->user()->nombre_visible ?? $request->user()->name;
        $victima?->notify(new FaltaJugadaContraTi($nombreAtacante, $cartaUsuario->tipoCarta->nombre, $jornadaEfecto));

        return response()->json(['message' => "Falta jugada. Afectará a la jornada {$jornadaEfecto}."]);
    }

    public function descartar(Request $request, CartaUsuario $cartaUsuario)
    {
        if ($error = $this->verificarPropiaEnMano($cartaUsuario, $request)) {
            return $error;
        }

        $cartaUsuario->update(['estado' => 'descartada']);

        return response()->json(['message' => 'Carta descartada.']);
    }

    public function jugar(Request $request, CartaUsuario $cartaUsuario)
    {
        if ($error = $this->verificarPropiaEnMano($cartaUsuario, $request)) {
            return $error;
        }

        $liga = $request->user()->ligaActiva;
        $topeMano = $liga->tope_mano_cartas ?? 8;

        $manoActual = CartaUsuario::where('id_liga', $liga->id)
            ->where('id_usuario', $request->user()->id)
            ->where('estado', 'en_mano')
            ->count();

        if ($manoActual > $topeMano) {
            return response()->json([
                'message' => "Tienes {$manoActual} cartas en mano y el límite es {$topeMano}. Descarta alguna antes de poder jugar otra.",
            ], 422);
        }

        $cartaUsuario->load('tipoCarta');
        $requierePartido = $this->motor->requiereEleccionDePartido($cartaUsuario->tipoCarta->codigo_efecto);

        if (! $requierePartido) {
            // Cartas de Forma 3 (ej. Crack): se juegan "en genérico" para la
            // próxima jornada jugable, sin elegir ningún partido concreto.
            $proximaJornada = $this->proximaJornadaNumero($liga);

            if (! $proximaJornada) {
                return response()->json(['message' => 'No hay ninguna jornada disponible para jugar esta carta ahora mismo.'], 422);
            }

            // Sin esta comprobación se podría jugar Crack o un Amuleto automático con la
            // jornada ya en marcha, sabiendo ya cuántos exactos llevas o qué partidos fallaste.
            if (CalendarioPartido::jornadaBloqueada($liga->id_temporada, $proximaJornada)) {
                return response()->json(['message' => 'Esa jornada ya ha empezado: las Jugadas se juegan antes de que empiece la jornada.'], 422);
            }

            if ($error = $this->comprobarExpulsion($liga, $request->user(), $proximaJornada, 'Jugadas')) {
                return $error;
            }

            if ($this->motorFaltas->estaBloqueadaPorSinComodines($liga->id, $request->user()->id, $proximaJornada, $cartaUsuario->tipoCarta->codigo_efecto)) {
                return response()->json(['message' => 'Tienes una Falta "Sin Comodines" activa esta jornada — no puedes jugar Amuleto ni Pleno Garantizado.'], 422);
            }

            $cartaUsuario->update([
                'estado' => 'jugada',
                'id_partido' => null,
                'jornada_efecto' => $proximaJornada,
                'jugada_en' => now(),
            ]);

            return response()->json(['message' => 'Carta jugada para la próxima jornada.']);
        }

        $validated = $request->validate([
            'id_partido' => ['required', 'exists:calendariopartidos,id'],
        ]);

        $partido = CalendarioPartido::findOrFail($validated['id_partido']);

        if ($partido->estado !== 'Programado') {
            return response()->json(['message' => 'Ese partido ya no admite jugar cartas sobre él.'], 422);
        }

        if (CalendarioPartido::jornadaBloqueada($partido->id_temporada, $partido->jornada)) {
            return response()->json(['message' => 'Esa jornada ya está bloqueada.'], 422);
        }

        if ($error = $this->comprobarExpulsion($liga, $request->user(), $partido->jornada, 'Jugadas')) {
            return $error;
        }

        if ($this->motorFaltas->estaBloqueadaPorSinComodines($liga->id, $request->user()->id, $partido->jornada, $cartaUsuario->tipoCarta->codigo_efecto)) {
            return response()->json(['message' => 'Tienes una Falta "Sin Comodines" activa esta jornada — no puedes jugar Amuleto ni Pleno Garantizado.'], 422);
        }

        $cartaUsuario->update([
            'estado' => 'jugada',
            'id_partido' => $partido->id,
            'jornada_efecto' => $partido->jornada,
            'jugada_en' => now(),
        ]);

        return response()->json(['message' => 'Carta jugada sobre ese partido.']);
    }

    /**
     * Resumen de una carta ya jugada, compartido por "Jugadas" (esperando) y el
     * "Historial" (ya resueltas), para que ambas pestañas cuenten lo mismo igual.
     */
    private function resumenCartaJugada(CartaUsuario $c, int $idUsuarioActual): array
    {
        $partidoPronosticado = $c->id_partido
            ? Pronostico::where('id_liga', $c->id_liga)->where('id_usuario', $c->id_usuario)->where('id_partido', $c->id_partido)->exists()
            : null;

        return [
            'id' => $c->id,
            'tipo_carta' => $c->tipoCarta,
            'estado' => $c->estado,
            'puntos_generados' => (int) $c->puntos_generados,
            'jugada_en' => $c->jugada_en?->toIso8601String(),
            'jornada_efecto' => $c->jornada_efecto,
            'partido' => $c->partido ? [
                'equipo_local' => $c->partido->equipoLocal->nombre_corto ?? $c->partido->equipoLocal->nombre,
                'equipo_visitante' => $c->partido->equipoVisitante->nombre_corto ?? $c->partido->equipoVisitante->nombre,
                'jornada' => $c->partido->jornada,
            ] : null,
            // Una carta jugada sobre un partido que no pronosticas se pierde: avisamos de ello
            'partido_pronosticado' => $partidoPronosticado,
            'objetivo' => $c->usuarioObjetivo ? [
                'nombre' => $c->usuarioObjetivo->nombre_visible ?? $c->usuarioObjetivo->name,
                'es_uno_mismo' => $c->usuarioObjetivo->id === $idUsuarioActual,
            ] : null,
            'mensaje_falta' => $c->mensaje_falta,
            // Por qué una carta resuelta como "no cumplida" no hizo nada — solo para Jugadas,
            // y solo cuando de verdad no tuvo efecto (no cuando el motivo ya lo cuenta el nota_carta).
            'motivo_sin_efecto' => $c->estado === 'resuelta_no_cumplida'
                ? $this->motivoSinEfecto($c->tipoCarta->codigo_efecto, $partidoPronosticado)
                : null,
        ];
    }

    /**
     * Explicación en lenguaje llano de por qué una Jugada no tuvo efecto, según el
     * tipo de carta y lo único que sabemos con certeza (si pronosticaste el partido).
     * No inventamos detalles que no tenemos guardados (p. ej. el resultado exacto que
     * hubiera hecho falta) — solo lo explicamos al nivel que podemos garantizar.
     */
    private function motivoSinEfecto(string $codigoEfecto, ?bool $partidoPronosticado): ?string
    {
        if ($partidoPronosticado === false) {
            return 'No llegaste a pronosticar ese partido, así que la carta no pudo aplicarse.';
        }

        return match (true) {
            str_contains($codigoEfecto, 'OJOHALCON') => 'No acertaste el resultado exacto de ese partido.',
            str_contains($codigoEfecto, 'DOBLETE') => 'Tu pronóstico de ese partido fue un fallo — no había puntos que duplicar.',
            str_contains($codigoEfecto, 'PLENOGARANTIZADO') => 'Ya habías acertado el resultado exacto por tu cuenta, así que la carta no tuvo nada que mejorar.',
            str_contains($codigoEfecto, 'PALOMITAS') => 'Tu pronóstico no coincidió con la mayoría de tu liga en ese partido (o nadie más había pronosticado todavía).',
            str_contains($codigoEfecto, 'CRACK') => 'No conseguiste el resultado exacto en 2 partidos de la jornada.',
            str_contains($codigoEfecto, 'AMIGOARBITRO') => 'No hubo tarjeta roja en ese partido.',
            str_contains($codigoEfecto, 'MADRUGADOR') || str_contains($codigoEfecto, 'FILODESCANSO') || str_contains($codigoEfecto, 'TIEMPO') => 'No hubo ningún gol en la franja de minutos de esta carta.',
            default => null,
        };
    }

    /**
     * Cuándo empieza de verdad una jornada: el primer partido de su "grueso principal".
     * Misma idea que CalendarioPartido::jornadaBloqueada() — un partido adelantado o
     * aplazado que caiga a más de 5 días de la mediana no cuenta como inicio.
     */
    private function inicioEfectivoDeJornada(Liga $liga, int $jornada): ?\Carbon\CarbonInterface
    {
        $partidos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->where('jornada', $jornada)
            ->whereNotNull('horario_estimado')
            ->get();

        if ($partidos->isEmpty()) {
            return null;
        }

        $timestamps = $partidos->map(fn ($p) => $p->horario_estimado->timestamp)->sort()->values();
        $mediana = $timestamps[intdiv($timestamps->count(), 2)];
        $ventanaSegundos = 5 * 24 * 60 * 60;

        return $partidos
            ->filter(fn ($p) => abs($p->horario_estimado->timestamp - $mediana) <= $ventanaSegundos)
            ->sortBy('horario_estimado')
            ->first()
            ?->horario_estimado;
    }

    /**
     * Las 2 comprobaciones que se repetían en jugar()/jugarFalta()/descartar():
     * que la carta sea de verdad tuya, y que siga en tu mano sin usar.
     */
    private function verificarPropiaEnMano(CartaUsuario $cartaUsuario, Request $request): ?JsonResponse
    {
        if ($cartaUsuario->id_usuario !== $request->user()->id) {
            return response()->json(['message' => 'Esta carta no es tuya.'], 403);
        }

        if ($cartaUsuario->estado !== 'en_mano') {
            return response()->json(['message' => 'Esta carta ya no está en tu mano.'], 422);
        }

        return null;
    }

    /**
     * La misma consulta de "próxima jornada con partidos por jugar" que se
     * repetía en 3 sitios distintos.
     */
    private function proximaJornadaNumero(Liga $liga): ?int
    {
        return CalendarioPartido::where('id_temporada', $liga->id_temporada)
            ->whereIn('estado', ['Programado', 'En juego'])
            ->orderBy('horario_estimado')
            ->value('jornada');
    }

    /**
     * La comprobación de Expulsión que se repetía en jugar() (×2 ramas) y
     * jugarFalta(), solo cambiando la categoría a comprobar.
     */
    private function comprobarExpulsion(Liga $liga, User $usuario, int $jornada, string $categoria): ?JsonResponse
    {
        if ($this->motorFaltas->estaExpulsadoDe($liga->id, $usuario->id, $jornada, $categoria)) {
            return response()->json(['message' => "Tienes una Expulsión activa esta jornada — no puedes jugar cartas de tipo {$categoria}."], 422);
        }

        return null;
    }
}