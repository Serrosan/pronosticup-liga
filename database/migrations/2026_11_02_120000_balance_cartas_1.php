<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Primer balance de las cartas (noviembre 2026). Los números de los efectos viven
 * en MotorFaltas y MotorEfectosCartas; aquí solo se ponen al día los textos del
 * catálogo y se crea la Todo queda en casa Rara.
 *
 * Se aplica a todas las cartas, también a las ya jugadas (decisión: más fácil y justo).
 * Se puede lanzar dos veces sin problema.
 */
return new class extends Migration
{
    /** codigo => [cantidad o puntos antes, después, descripción nueva] */
    private const CAMBIOS = [
        'FAL-COM-CASA' => [2, 3, 'Tu rival debe incluir al menos 3 victorias locales entre sus pronósticos de la jornada siguiente.'],
        'FAL-PCOM-CASA' => [3, 4, 'Tu rival debe incluir al menos 4 victorias locales entre sus pronósticos de la jornada siguiente.'],
        'FAL-LEG-CASA' => [5, 6, 'Tu rival debe incluir al menos 6 victorias locales entre sus pronósticos de la jornada siguiente.'],
        'FAL-COM-VISITA' => [2, 3, 'Tu rival debe incluir al menos 3 victorias visitantes entre sus pronósticos de la jornada siguiente.'],
        'FAL-PCOM-VISITA' => [3, 4, 'Tu rival debe incluir al menos 4 victorias visitantes entre sus pronósticos de la jornada siguiente.'],
        'FAL-RAR-VISITA' => [4, 5, 'Tu rival debe incluir al menos 5 victorias visitantes entre sus pronósticos de la jornada siguiente.'],
        'FAL-LEG-VISITA' => [5, 6, 'Tu rival debe incluir al menos 6 victorias visitantes entre sus pronósticos de la jornada siguiente.'],
        'JUG-COM-OJOHALCON' => [2, 3, '+3 puntos si aciertas el resultado exacto del partido que elijas.'],
        'JUG-PCOM-OJOHALCON' => [4, 5, '+5 puntos si aciertas el resultado exacto del partido que elijas.'],
        'JUG-LEG-CRACK' => [4, 6, '+6 puntos si aciertas el resultado exacto de 2 partidos de la jornada, sean cuales sean (no se eligen antes).'],
    ];

    private const CASA_RARA = 'Tu rival debe incluir al menos 5 victorias locales entre sus pronósticos de la jornada siguiente.';

    public function up(): void
    {
        foreach (self::CAMBIOS as $codigo => [$antes, $despues, $descripcion]) {
            $this->actualizar($codigo, $antes, $despues, $descripcion);
        }

        // Nueva: Todo queda en casa Rara, copiando imagen y categoría de sus hermanas.
        if (! DB::table('tipos_carta')->where('codigo_efecto', 'FAL-RAR-CASA')->exists()) {
            $modelo = DB::table('tipos_carta')->whereIn('codigo_efecto', ['FAL-PCOM-CASA', 'FAL-COM-CASA', 'FAL-LEG-CASA'])
                ->orderByRaw("CASE codigo_efecto WHEN 'FAL-PCOM-CASA' THEN 0 WHEN 'FAL-COM-CASA' THEN 1 ELSE 2 END")->first();

            if ($modelo) {
                DB::table('tipos_carta')->insert([
                    'id_categoria' => $modelo->id_categoria,
                    'rareza' => 'Rara',
                    'nombre' => $modelo->nombre,
                    'descripcion' => self::CASA_RARA,
                    'imagen_url' => $modelo->imagen_url ?? null,
                    'insignia_corta' => $this->cambiarNumero($modelo->insignia_corta ?? null, $this->cantidadDe($modelo->codigo_efecto), 5),
                    'codigo_efecto' => 'FAL-RAR-CASA',
                    'activa' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::CAMBIOS as $codigo => [$antes, $despues]) {
            $fila = DB::table('tipos_carta')->where('codigo_efecto', $codigo)->first();
            if ($fila) {
                DB::table('tipos_carta')->where('id', $fila->id)->update([
                    'descripcion' => $this->cambiarNumero($fila->descripcion, $despues, $antes),
                    'insignia_corta' => $this->cambiarNumero($fila->insignia_corta ?? null, $despues, $antes),
                    'updated_at' => now(),
                ]);
            }
        }
        DB::table('tipos_carta')->where('codigo_efecto', 'FAL-RAR-CASA')->whereNotExists(
            fn ($q) => $q->from('cartas_usuario')->whereColumn('cartas_usuario.id_tipo_carta', 'tipos_carta.id')
        )->delete();
    }

    private function actualizar(string $codigo, int $antes, int $despues, string $descripcion): void
    {
        $fila = DB::table('tipos_carta')->where('codigo_efecto', $codigo)->first();
        if (! $fila || $fila->descripcion === $descripcion) {
            return;
        }

        DB::table('tipos_carta')->where('id', $fila->id)->update([
            'descripcion' => $descripcion,
            // La insignia corta suele llevar el número ("+2", "2🏠"…): se cambia solo si lo lleva.
            'insignia_corta' => $this->cambiarNumero($fila->insignia_corta ?? null, $antes, $despues),
            'updated_at' => now(),
        ]);
    }

    private function cambiarNumero(?string $texto, int $antes, int $despues): ?string
    {
        return $texto === null ? null : preg_replace('/(?<!\d)'.$antes.'(?!\d)/u', (string) $despues, $texto);
    }

    private function cantidadDe(string $codigo): int
    {
        return self::CAMBIOS[$codigo][1] ?? 0;
    }
};
