<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use App\Models\GoleadorJornada;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\RecordatorioEnviado;
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
}
