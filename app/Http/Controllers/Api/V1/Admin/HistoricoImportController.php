<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Liga;
use App\Models\Pronostico;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HistoricoImportController extends Controller
{
    public function importar(Request $request)
    {
        $validated = $request->validate([
            'id_liga' => ['required', 'exists:ligas,id'],
            'email' => ['required', 'email'],
            'texto' => ['required', 'string'],
        ]);

        $liga = Liga::find($validated['id_liga']);
        $usuario = User::where('email', $validated['email'])->first();

        if (! $usuario) {
            return response()->json(['message' => "No existe ningún usuario registrado con el email '{$validated['email']}'."], 404);
        }

        $lineas = array_filter(array_map('rtrim', explode("\n", str_replace("\r", '', $validated['texto']))));

        if (empty($lineas)) {
            return response()->json(['message' => 'El texto pegado está vacío.'], 422);
        }

        array_shift($lineas); // quitamos la línea de cabecera ("Jornada  Partido  ...")

        $esMiembro = $liga->usuarios()->where('id_usuario', $usuario->id)->exists();
        if (! $esMiembro) {
            $liga->usuarios()->attach($usuario->id, ['rol' => 'Miembro']);
        }

        $creados = 0;
        $omitidos = 0;
        $partidosNoEncontrados = [];

        foreach ($lineas as $linea) {
            $columnas = preg_split('/\t/', $linea);

            if (count($columnas) < 4) {
                $omitidos++;
                continue;
            }

            $jornada = (int) trim($columnas[0]);
            $textoPartido = trim($columnas[1]);
            $golesLocal = trim($columnas[2]);
            $golesVisitante = trim($columnas[3]);

            if ($golesLocal === '' || $golesVisitante === '' || ! is_numeric($golesLocal) || ! is_numeric($golesVisitante)) {
                $omitidos++;
                continue;
            }

            [$nombreLocal, $nombreVisitante] = $this->separarPartido($textoPartido);

            if (! $nombreLocal) {
                $partidosNoEncontrados[] = "J{$jornada}: {$textoPartido} (no se pudo separar el nombre)";
                $omitidos++;
                continue;
            }

            $partido = $this->buscarPartido($liga->id_temporada, $jornada, $nombreLocal, $nombreVisitante);

            if (! $partido) {
                $partidosNoEncontrados[] = "J{$jornada}: {$textoPartido}";
                $omitidos++;
                continue;
            }

            Pronostico::updateOrCreate(
                ['id_usuario' => $usuario->id, 'id_liga' => $liga->id, 'id_partido' => $partido->id],
                [
                    'resultado_1x2' => $this->calcularResultado((int) $golesLocal, (int) $golesVisitante),
                    'goles_local_predicho' => (int) $golesLocal,
                    'goles_visitante_predicho' => (int) $golesVisitante,
                    'enviado_en' => now(),
                ]
            );

            $creados++;
        }

        return response()->json([
            'message' => "{$creados} pronóstico(s) importados para {$usuario->name}. {$omitidos} omitido(s).",
            'creados' => $creados,
            'omitidos' => $omitidos,
            'partidos_no_encontrados' => array_values(array_unique($partidosNoEncontrados)),
        ]);
    }

    private function separarPartido(string $texto): array
    {
        foreach ([' - ', ' vs. ', ' vs ', ' VS ', ' V. '] as $separador) {
            if (str_contains($texto, $separador)) {
                [$local, $visitante] = explode($separador, $texto, 2);
                return [trim($local), trim($visitante)];
            }
        }

        return [null, null];
    }

    private function buscarPartido(int $idTemporada, int $jornada, string $nombreLocal, string $nombreVisitante)
    {
        $partidos = CalendarioPartido::where('id_temporada', $idTemporada)
            ->where('jornada', $jornada)
            ->with(['equipoLocal', 'equipoVisitante'])
            ->get();

        foreach ($partidos as $p) {
            if ($this->coincideNombre($nombreLocal, $p->equipoLocal) && $this->coincideNombre($nombreVisitante, $p->equipoVisitante)) {
                return $p;
            }
        }

        return null;
    }

    private function coincideNombre(string $nombreExcel, $equipo): bool
    {
        $normalizar = fn ($t) => Str::of($t)->lower()->ascii()->replace(['cf', 'fc', 'club de futbol', 'club', '.'], '')->squish()->toString();

        $excelNorm = $normalizar($nombreExcel);
        $completoNorm = $normalizar($equipo->nombre);
        $cortoNorm = $normalizar($equipo->nombre_corto ?? '');

        return str_contains($completoNorm, $excelNorm) || str_contains($excelNorm, $completoNorm)
            || ($cortoNorm && (str_contains($cortoNorm, $excelNorm) || str_contains($excelNorm, $cortoNorm)));
    }

    private function calcularResultado(int $golesLocal, int $golesVisitante): string
    {
        if ($golesLocal > $golesVisitante) return 'Local';
        if ($golesLocal < $golesVisitante) return 'Visitante';
        return 'Empate';
    }
}