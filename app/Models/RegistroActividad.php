<?php

namespace App\Observers;

use App\Models\RegistroActividad;
use Illuminate\Database\Eloquent\Model;

class RegistroActividadObserver
{
    public function created(Model $model): void
    {
        $this->registrar('creado', $model);
    }

    public function updated(Model $model): void
    {
        $cambiosBrutos = $model->getChanges();
        unset($cambiosBrutos['updated_at']);

        if (empty($cambiosBrutos)) {
            return;
        }

        // Guardamos "antes → después" de cada campo, no solo el valor nuevo — mucho
        // más útil para leer el historial de un vistazo (ej. estado: en_mano → jugada).
        $cambiosConAntes = [];
        foreach ($cambiosBrutos as $campo => $valorNuevo) {
            $cambiosConAntes[$campo] = [
                'antes' => $model->getOriginal($campo),
                'despues' => $valorNuevo,
            ];
        }

        $this->registrar('actualizado', $model, $cambiosConAntes);
    }

    public function deleted(Model $model): void
    {
        $this->registrar('eliminado', $model);
    }

    private function registrar(string $accion, Model $model, ?array $cambios = null): void
    {
        RegistroActividad::create([
            'id_usuario' => auth()->id(),
            'accion' => $accion,
            'modelo' => class_basename($model),
            'id_registro' => $model->getKey(),
            'cambios' => $cambios,
            'creado_en' => now(),
        ]);
    }
}