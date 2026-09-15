<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Arbitro extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'apellidos', 'nacionalidad', 'comunidad_autonoma',
        'anio_debut', 'promedio_tarjetas_amarillas', 'promedio_tarjetas_rojas', 'imagen',
    ];

    /**
     * Busca un árbitro ya existente por nombre completo (comparación flexible, sin
     * mayúsculas/tildes). Si no existe todavía, lo crea con una separación simple
     * (primera palabra = nombre, resto = apellidos) — puede no ser perfecta para
     * nombres compuestos, pero evita perder la referencia al árbitro real.
     */
    public static function resolverPorNombre(string $nombreCompleto): int
    {
        $normalizar = fn ($texto) => Str::of($texto)->lower()->ascii()->squish()->toString();
        $buscado = $normalizar($nombreCompleto);

        $arbitro = self::all()->first(function ($a) use ($normalizar, $buscado) {
            $nombreCompletoArbitro = $normalizar(trim("{$a->nombre} {$a->apellidos}"));
            return $nombreCompletoArbitro === $buscado;
        });

        if ($arbitro) {
            return $arbitro->id;
        }

        $partes = explode(' ', trim($nombreCompleto), 2);

        $arbitroNuevo = self::create([
            'nombre' => $partes[0],
            'apellidos' => $partes[1] ?? '',
        ]);

        return $arbitroNuevo->id;
    }
}