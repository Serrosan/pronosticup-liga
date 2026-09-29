<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CartaUsuario;
use App\Models\TipoCarta;
use Illuminate\Http\Request;

class CatalogoCartasController extends Controller
{
    public function index(Request $request)
    {
        $tipos = TipoCarta::where('activa', true)
            ->with('categoria')
            ->get();

        // Para marcar "la tienes ahora" / "ya la has tenido" — sin usuario autenticado
        // (no debería pasar en esta ruta, pero por si acaso) simplemente no se marca nada.
        $idsTenidasAlgunaVez = collect();
        $idsEnPosesionAhora = collect();

        if ($request->user()) {
            $poseidas = CartaUsuario::where('id_usuario', $request->user()->id)->get(['id_tipo_carta', 'estado']);
            $idsTenidasAlgunaVez = $poseidas->pluck('id_tipo_carta')->unique();
            $idsEnPosesionAhora = $poseidas->whereIn('estado', ['en_mano', 'jugada'])->pluck('id_tipo_carta')->unique();
        }

        $data = $tipos->map(fn ($t) => [
            'id' => $t->id,
            'nombre' => $t->nombre,
            'descripcion' => $t->descripcion,
            'imagen_url' => $t->imagen_url,
            'insignia_corta' => $t->insignia_corta,
            'rareza' => $t->rareza,
            'categoria_nombre' => $t->categoria->nombre ?? '—',
            'categoria_icono' => $t->categoria->icono ?? null,
            'la_tienes' => $idsEnPosesionAhora->contains($t->id),
            'la_has_tenido' => $idsTenidasAlgunaVez->contains($t->id),
        ]);

        return response()->json(['data' => $data]);
    }
}