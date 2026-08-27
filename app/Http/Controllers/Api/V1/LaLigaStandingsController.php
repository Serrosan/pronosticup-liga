<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CalendarioPartido;
use App\Models\Equipo;
use Illuminate\Http\Request;

class LaLigaStandingsController extends Controller
{
    public function index(Request $request)
    {
        $liga = $request->user()->ligaActiva;

        if (! $liga) {
            return response()->json(['message' => 'No tienes ninguna liga activa.'], 409);
        }

        $idTemporada = $liga->id_temporada;

        $vacio = fn () => ['pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0, 'gf' => 0, 'gc' => 0, 'pts' => 0, 'forma' => []];

        $equipos = Equipo::all(['id', 'nombre', 'nombre_corto', 'escudo_url']);

        $general = [];
        $casa = [];
        $fuera = [];
        foreach ($equipos as $equipo) {
            $base = ['id' => $equipo->id, 'nombre' => $equipo->nombre_corto ?? $equipo->nombre, 'escudo_url' => $equipo->escudo_url];
            $general[$equipo->id] = array_merge($base, $vacio());
            $casa[$equipo->id] = array_merge($base, $vacio());
            $fuera[$equipo->id] = array_merge($base, $vacio());
        }

        $partidos = CalendarioPartido::where('id_temporada', $idTemporada)
            ->where('estado', 'Jugado')
            ->orderBy('horario_estimado')
            ->get();

        foreach ($partidos as $p) {
            $local = $p->id_equipo_local;
            $visitante = $p->id_equipo_visitante;

            foreach ([['id' => $local, 'gf' => $p->goles_casa, 'gc' => $p->goles_fuera, 'tabla' => &$casa],
                      ['id' => $visitante, 'gf' => $p->goles_fuera, 'gc' => $p->goles_casa, 'tabla' => &$fuera]] as $lado) {
                $id = $lado['id'];
                $general[$id]['pj']++;
                $general[$id]['gf'] += $lado['gf'];
                $general[$id]['gc'] += $lado['gc'];
                $lado['tabla'][$id]['pj']++;
                $lado['tabla'][$id]['gf'] += $lado['gf'];
                $lado['tabla'][$id]['gc'] += $lado['gc'];

                if ($lado['gf'] > $lado['gc']) {
                    $general[$id]['pg']++; $general[$id]['pts'] += 3; $general[$id]['forma'][] = 'G';
                    $lado['tabla'][$id]['pg']++; $lado['tabla'][$id]['pts'] += 3; $lado['tabla'][$id]['forma'][] = 'G';
                } elseif ($lado['gf'] < $lado['gc']) {
                    $general[$id]['pp']++; $general[$id]['forma'][] = 'P';
                    $lado['tabla'][$id]['pp']++; $lado['tabla'][$id]['forma'][] = 'P';
                } else {
                    $general[$id]['pe']++; $general[$id]['pts'] += 1; $general[$id]['forma'][] = 'E';
                    $lado['tabla'][$id]['pe']++; $lado['tabla'][$id]['pts'] += 1; $lado['tabla'][$id]['forma'][] = 'E';
                }
            }
        }

        $ordenar = fn ($tabla) => collect($tabla)
            ->map(fn ($s) => array_merge($s, ['dg' => $s['gf'] - $s['gc'], 'forma' => array_slice($s['forma'], -5)]))
            ->sortBy([['pts', 'desc'], ['dg', 'desc'], ['gf', 'desc']])
            ->values();

        return response()->json([
            'data' => [
                'general' => $ordenar($general),
                'casa' => $ordenar($casa),
                'fuera' => $ordenar($fuera),
            ],
        ]);
    }
}