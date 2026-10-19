<?php

namespace App\Services;

use App\Models\AvisoProgramado;
use App\Models\Liga;
use App\Models\PushSuscripcion;
use App\Models\User;
use App\Notifications\AvisoDelAdmin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/** Envía los avisos del admin, al momento o los programados que ya toca. */
class AvisosDelAdminService
{
    /** @return Collection<int,User> */
    public function destinatarios(string $para): Collection
    {
        $consulta = $para === 'todos'
            ? User::query()
            : (Liga::find((int) $para)?->usuarios() ?? User::query()->whereRaw('1 = 0'));

        return $consulta->whereNull('desactivada_en')->get();
    }

    /** @return array{personas:int, con_movil:int} */
    public function enviar(string $para, string $titulo, string $mensaje, ?string $url): array
    {
        $usuarios = $this->destinatarios($para);

        if ($usuarios->isNotEmpty()) {
            Notification::send($usuarios, new AvisoDelAdmin($titulo, $mensaje, $url));
        }

        return [
            'personas' => $usuarios->count(),
            'con_movil' => PushSuscripcion::whereIn('id_usuario', $usuarios->pluck('id'))->distinct()->count('id_usuario'),
        ];
    }

    /** Los programados cuya hora ya ha llegado. Cada uno se marca antes de enviarlo, para no mandarlo dos veces. */
    public function enviarPendientes(): int
    {
        $enviados = 0;

        foreach (AvisoProgramado::whereNull('enviado_en')->where('enviar_en', '<=', now())->orderBy('enviar_en')->get() as $aviso) {
            $marcado = AvisoProgramado::whereKey($aviso->id)->whereNull('enviado_en')->update(['enviado_en' => now()]);
            if ($marcado !== 1) {
                continue;
            }

            $resultado = $this->enviar($aviso->para, $aviso->titulo, $aviso->mensaje, $aviso->url);
            $aviso->update(['personas' => $resultado['personas']]);
            Log::info('Aviso programado enviado', ['aviso' => $aviso->id, 'personas' => $resultado['personas']]);
            $enviados++;
        }

        return $enviados;
    }
}
