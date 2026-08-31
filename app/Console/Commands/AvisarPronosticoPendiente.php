<?php

namespace App\Console\Commands;

use App\Models\CalendarioPartido;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Notifications\JornadaProximaACerrar;
use Illuminate\Console\Command;

class AvisarPronosticoPendiente extends Command
{
    protected $signature = 'liga:avisar-pronostico-pendiente {--horas=3}';
    protected $description = 'Avisa a los usuarios que aún no han pronosticado partidos que empiezan pronto';

    public function handle()
    {
        $horas = (int) $this->option('horas');
        $limite = now()->addHours($horas);

        $ligas = Liga::whereHas('usuarios')->get();
        $avisados = 0;

        foreach ($ligas as $liga) {
            $partidosProximos = CalendarioPartido::where('id_temporada', $liga->id_temporada)
                ->where('estado', 'Programado')
                ->whereBetween('horario_estimado', [now(), $limite])
                ->get();

            if ($partidosProximos->isEmpty()) {
                continue;
            }

            $jornada = $partidosProximos->first()->jornada;

            foreach ($liga->usuarios as $usuario) {
                $pronosticados = Pronostico::where('id_usuario', $usuario->id)
                    ->where('id_liga', $liga->id)
                    ->whereIn('id_partido', $partidosProximos->pluck('id'))
                    ->count();

                $pendientes = $partidosProximos->count() - $pronosticados;

                if ($pendientes > 0) {
                    $usuario->notify(new JornadaProximaACerrar($jornada, $pendientes));
                    $avisados++;
                }
            }
        }

        $this->info("Avisos enviados: {$avisados}");
    }
}