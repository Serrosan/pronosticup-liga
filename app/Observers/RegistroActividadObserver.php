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
        $this->registrar('actualizado', $model, $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->registrar('eliminado', $model);
    }

    private function registrar(string $accion, Model $model, ?array $cambios = null): void
    {
        if ($cambios) {
            unset($cambios['updated_at']);
            if (empty($cambios)) {
                return;
            }
        }

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