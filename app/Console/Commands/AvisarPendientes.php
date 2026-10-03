<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use App\Models\CartaUsuario;
use App\Models\GoleadorJornada;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\RecordatorioEnviado;
use App\Notifications\CartasSinJugar;
use App\Notifications\RecordatorioPendiente;
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

        $this->info("{$avisosEnviados} recordatorio(s) enviados.");
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
     * Avisa por la campana a quien tenga cartas de Jugadas en la mano justo antes de que
     * empiece la jornada (después ya no se pueden jugar). No usa RecordatorioEnviado: se
     * comprueba en las propias notificaciones si ya se avisó por esta jornada y esta liga.
     */
    private function avisarCartasSinJugar(Liga $liga, int $jornada): int
    {
        $sinJugarPorUsuario = CartaUsuario::where('id_liga', $liga->id)
            ->where('estado', 'en_mano')
            ->whereHas('tipoCarta.categoria', fn ($q) => $q->where('nombre', 'Jugadas'))
            ->selectRaw('id_usuario, COUNT(*) as total')
            ->groupBy('id_usuario')
            ->pluck('total', 'id_usuario');

        $enviados = 0;

        foreach ($liga->usuarios as $usuario) {
            $cantidad = (int) ($sinJugarPorUsuario[$usuario->id] ?? 0);

            if ($cantidad === 0) {
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

                $usuario->notify(new CartasSinJugar($jornada, $liga->id, $cantidad, $liga->nombre));
                $enviados++;
            } catch (\Throwable $e) {
                $this->error("Fallo avisando de cartas sin jugar a {$usuario->email} (liga {$liga->id}, J{$jornada}): {$e->getMessage()}");
                report($e);
            }
        }

        return $enviados;
    }
}