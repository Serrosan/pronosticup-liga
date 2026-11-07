<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\GoleadorJornada;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\RecordatorioEnviado;
use App\Notifications\CartasSinJugar;
use App\Notifications\JornadaAbierta;
use App\Notifications\RecordatorioPendiente;
use App\Services\AperturaJornadas;
use Illuminate\Console\Command;

class AvisarPendientes extends Command
{
    protected $signature = 'liga:avisar-pendientes';
    protected $description = 'Avisa a usuarios con pronósticos o goleadores pendientes, 24h/6h/2h antes del inicio de la jornada';

    private const VENTANAS = [24, 6, 2];

    public function handle(): void
    {
        $ligas = Liga::with('usuarios')->get();
        $avisosEnviados = 0;

        foreach ($ligas as $liga) {
            $jornada = $this->proximaJornadaPorEmpezar($liga->id_temporada);

            if (! $jornada) {
                continue;
            }

            [$numeroJornada, $horarioPrimerPartido] = $jornada;
            $horasRestantes = now()->diffInHours($horarioPrimerPartido, false);

            if ($horasRestantes <= 0) {
                continue;
            }

            foreach (self::VENTANAS as $ventana) {
                if ($horasRestantes > $ventana) {
                    continue;
                }

                foreach ($liga->usuarios as $usuario) {
                    foreach (['pronosticos', 'goleadores'] as $tipo) {
                        try {
                            $avisosEnviados += $this->avisarSiHaceFalta($usuario, $liga, $numeroJornada, $ventana, $tipo);
                        } catch (\Throwable $e) {
                            $this->error("Fallo avisando a {$usuario->email} ({$tipo}, liga {$liga->id}, J{$numeroJornada}): {$e->getMessage()}");
                            report($e);
                        }
                    }
                }
            }

            // Recordatorio de cartas sin jugar: una sola vez por jornada, en las últimas 6 horas,
            // y solo en ligas con el modo Cartas. Va aparte de los recordatorios de arriba.
            if ($liga->tipo === 'ConExtras' && $horasRestantes <= 6) {
                $avisosEnviados += $this->avisarCartasSinJugar($liga, $numeroJornada);
            }
        }

        $avisosEnviados += $this->avisarJornadasAbiertas();

        $this->info("{$avisosEnviados} recordatorio(s) enviados.");
    }

    /**
     * Ligas con cartas: en cuanto termina una jornada se abre la siguiente para
     * pronosticar (AperturaJornadas). Se avisa una vez por liga y jornada.
     */
    private function avisarJornadasAbiertas(): int
    {
        $enviados = 0;

        foreach (Liga::where('tipo', 'ConExtras')->with('usuarios')->get() as $liga) {
            $jornada = CalendarioPartido::proximaJornadaPorJugar((int) $liga->id_temporada);

            if (! $jornada || CalendarioPartido::jornadaBloqueada($liga->id_temporada, $jornada)
                || app(AperturaJornadas::class)->motivoCerrada($liga, $jornada) !== null) {
                continue;
            }

            foreach ($liga->usuarios as $usuario) {
                try {
                    $yaAvisado = $usuario->notifications()
                        ->where('type', JornadaAbierta::class)
                        ->where('data->jornada', $jornada)
                        ->where('data->id_liga', $liga->id)
                        ->exists();

                    if (! $yaAvisado) {
                        $usuario->notify(new JornadaAbierta($jornada, $liga->id, $liga->nombre));
                        $enviados++;
                    }
                } catch (\Throwable $e) {
                    $this->error("Fallo avisando de la jornada abierta a {$usuario->email} (liga {$liga->id}, J{$jornada}): {$e->getMessage()}");
                    report($e);
                }
            }
        }

        return $enviados;
    }

    private function proximaJornadaPorEmpezar(int $idTemporada): ?array
    {
        $proximoPartido = CalendarioPartido::where('id_temporada', $idTemporada)
            ->where('estado', 'Programado')
            ->orderBy('horario_estimado')
            ->first();

        if (! $proximoPartido) {
            return null;
        }

        return [$proximoPartido->jornada, $proximoPartido->horario_estimado];
    }

    private function avisarSiHaceFalta($usuario, Liga $liga, int $jornada, int $ventana, string $tipo): int
    {
        $yaEnviado = RecordatorioEnviado::where('id_usuario', $usuario->id)
            ->where('id_liga', $liga->id)
            ->where('jornada', $jornada)
            ->where('tipo', $tipo)
            ->where('ventana_horas', $ventana)
            ->exists();

        if ($yaEnviado) {
            return 0;
        }

        $leFalta = $tipo === 'goleadores'
            ? $this->faltanGoleadores($usuario->id, $liga->id, $jornada)
            : $this->faltanPronosticos($usuario->id, $liga->id, $liga->id_temporada, $jornada);

        if (! $leFalta) {
            return 0;
        }

        $usuario->notify(new RecordatorioPendiente($jornada, $liga->nombre, $tipo, $ventana));

        RecordatorioEnviado::create([
            'id_usuario' => $usuario->id,
            'id_liga' => $liga->id,
            'jornada' => $jornada,
            'tipo' => $tipo,
            'ventana_horas' => $ventana,
            'enviado_en' => now(),
        ]);

        return 1;
    }

    private function faltanPronosticos(int $idUsuario, int $idLiga, int $idTemporada, int $jornada): bool
    {
        $idsPartidos = CalendarioPartido::where('id_temporada', $idTemporada)
            ->where('jornada', $jornada)
            ->pluck('id');

        $totalPronosticados = Pronostico::where('id_usuario', $idUsuario)
            ->where('id_liga', $idLiga)
            ->whereIn('id_partido', $idsPartidos)
            ->count();

        return $totalPronosticados < $idsPartidos->count();
    }

    private function faltanGoleadores(int $idUsuario, int $idLiga, int $jornada): bool
    {
        $total = GoleadorJornada::where('id_usuario', $idUsuario)
            ->where('id_liga', $idLiga)
            ->where('jornada', $jornada)
            ->count();

        return $total < 5;
    }

    /**
     * Avisa por la campana de las cartas que todavía PUEDES jugar antes de que empiece la
     * jornada, con los mismos límites que al jugarlas: 2 Jugadas por jornada (y no si te
     * han expulsado) y 1 Falta, que afecta a la jornada siguiente. Una vez por jornada y
     * liga: se comprueba en las propias notificaciones si ya se avisó.
     */
    private function avisarCartasSinJugar(Liga $liga, int $jornada): int
    {
        $motor = app(\App\Services\MotorFaltas::class);
        $enMano = fn (string $categoria) => CartaUsuario::where('id_liga', $liga->id)
            ->where('estado', 'en_mano')
            ->whereHas('tipoCarta.categoria', fn ($q) => $q->where('nombre', $categoria))
            ->selectRaw('id_usuario, COUNT(*) as total')->groupBy('id_usuario')->pluck('total', 'id_usuario');
        $jugadas = fn (string $categoria, int $jornadaEfecto) => CartaUsuario::where('id_liga', $liga->id)
            ->where('estado', 'jugada')->where('jornada_efecto', $jornadaEfecto)
            ->whereHas('tipoCarta.categoria', fn ($q) => $q->where('nombre', $categoria))
            ->selectRaw('id_usuario, COUNT(*) as total')->groupBy('id_usuario')->pluck('total', 'id_usuario');

        $jugadasEnMano = $enMano('Jugadas');
        $faltasEnMano = $enMano('Faltas');
        $jugadasYa = $jugadas('Jugadas', $jornada);
        $faltasYa = $jugadas('Faltas', $jornada + 1); // las Faltas de ahora afectan a la siguiente

        $enviados = 0;

        foreach ($liga->usuarios as $usuario) {
            $manoJugadas = (int) ($jugadasEnMano[$usuario->id] ?? 0);
            $huecos = max(0, \App\Http\Controllers\Api\V1\MisCartasController::LIMITE_JUGADAS_POR_JORNADA - (int) ($jugadasYa[$usuario->id] ?? 0));
            $jugadasPosibles = min($huecos, $manoJugadas);
            $faltaPosible = (int) ($faltasEnMano[$usuario->id] ?? 0) > 0 && (int) ($faltasYa[$usuario->id] ?? 0) === 0;

            if ($jugadasPosibles > 0 && $motor->estaExpulsadoDe($liga->id, $usuario->id, $jornada, 'Jugadas')) {
                $jugadasPosibles = 0;
            }
            if ($faltaPosible && $motor->estaExpulsadoDe($liga->id, $usuario->id, $jornada, 'Faltas')) {
                $faltaPosible = false;
            }
            if ($jugadasPosibles === 0 && ! $faltaPosible) {
                continue;
            }

            try {
                $yaAvisado = $usuario->notifications()
                    ->where('type', CartasSinJugar::class)
                    ->where('data->jornada', $jornada)
                    ->where('data->id_liga', $liga->id)
                    ->exists();

                if ($yaAvisado) {
                    continue;
                }

                $usuario->notify(new CartasSinJugar($jornada, $liga->id, $liga->nombre, $jugadasPosibles, $manoJugadas, $faltaPosible));
                $enviados++;
            } catch (\Throwable $e) {
                $this->error("Fallo avisando de cartas sin jugar a {$usuario->email} (liga {$liga->id}, J{$jornada}): {$e->getMessage()}");
                report($e);
            }
        }

        return $enviados;
    }
}