<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarioPartido extends Model
{
    use HasFactory;

    protected $table = 'calendariopartidos';

    protected $fillable = [
        'id_temporada', 'id_equipo_local', 'id_equipo_visitante', 'id_estadio',
        'jornada', 'horario_estimado', 'horario_oficial', 'id_arbitro',
        'goles_casa', 'goles_fuera', 'estado', 'asistencia', 'id_externo_api',
        'video_resumen_url', 'sincronizado_en', 'minuto_partido',
    ];

    public function equipoLocal()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo_local');
    }

    public function equipoVisitante()
    {
        return $this->belongsTo(Equipo::class, 'id_equipo_visitante');
    }

    public function estadio()
    {
        return $this->belongsTo(Estadio::class, 'id_estadio');
    }

    public function arbitro()
    {
        return $this->belongsTo(Arbitro::class, 'id_arbitro');
    }

    protected function casts(): array
    {
        return [
            'horario_estimado' => 'datetime',
            'horario_oficial' => 'datetime',
            'sincronizado_en' => 'datetime',
        ];
    }

    /**
     * Comprueba si una jornada está bloqueada para pronósticos/goleadores.
     * Ignora partidos "atípicos" (adelantados o aplazados) que caigan muy lejos
     * en el tiempo del grueso principal de la jornada, usando la mediana de
     * horarios como referencia — así un partido jugado 2 semanas antes que el
     * resto no bloquea al resto de la jornada.
     */
    public static function jornadaBloqueada(int $idTemporada, int $jornada): bool
    {
        $partidos = self::where('id_temporada', $idTemporada)
            ->where('jornada', $jornada)
            ->get();

        if ($partidos->isEmpty()) {
            return false;
        }

        $timestamps = $partidos->pluck('horario_estimado')
            ->filter()
            ->map(fn ($h) => $h->timestamp)
            ->sort()
            ->values();

        if ($timestamps->isEmpty()) {
            return $partidos->contains(fn ($p) => $p->estado !== 'Programado');
        }

        $medianTimestamp = $timestamps[intdiv($timestamps->count(), 2)];
        $ventanaSegundos = 5 * 24 * 60 * 60; // 5 días de margen

        $partidosPrincipales = $partidos->filter(function ($p) use ($medianTimestamp, $ventanaSegundos) {
            if (! $p->horario_estimado) {
                return true;
            }
            return abs($p->horario_estimado->timestamp - $medianTimestamp) <= $ventanaSegundos;
        });

        return $partidosPrincipales->contains(fn ($p) => $p->estado !== 'Programado');
    }
}