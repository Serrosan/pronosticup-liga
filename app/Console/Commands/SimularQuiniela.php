<?php

namespace App\Console\Commands;

use App\Models\Equipo;
use App\Models\Liga;
use App\Models\PrediccionQuiniela;
use App\Models\QuinielaPosiciones;
use Illuminate\Console\Command;

class SimularQuiniela extends Command
{
    protected $signature = 'liga:simular-quiniela {tipo} {--liga=}';
    protected $description = 'Rellena una quiniela abierta con predicciones de prueba de todos los usuarios, sin resolverla';

    public function handle()
    {
        $tipo = $this->argument('tipo');

        $liga = $this->option('liga')
            ? Liga::findOrFail($this->option('liga'))
            : Liga::first();

        $quiniela = QuinielaPosiciones::where('id_liga', $liga->id)->where('tipo', $tipo)->first();

        if (! $quiniela) {
            $this->error("No existe la quiniela \"{$tipo}\" para esta liga todavía. Ábrela primero desde el admin.");
            return 1;
        }

        if (! $quiniela->abierta) {
            $this->error('Esta quiniela no está abierta. Ábrela desde el admin antes de simular.');
            return 1;
        }

        $equipos = Equipo::inRandomOrder()->get();
        $usuarios = $liga->usuarios;
        $creadas = 0;

        foreach ($usuarios as $usuario) {
            $yaExiste = PrediccionQuiniela::where('id_quiniela', $quiniela->id)
                ->where('id_usuario', $usuario->id)
                ->exists();

            if ($yaExiste) {
                continue;
            }

            $ordenAleatorio = $equipos->shuffle();

            foreach ($ordenAleatorio->values() as $indice => $equipo) {
                PrediccionQuiniela::create([
                    'id_quiniela' => $quiniela->id,
                    'id_usuario' => $usuario->id,
                    'id_equipo' => $equipo->id,
                    'posicion_predicha' => $indice + 1,
                ]);
            }

            $creadas++;
        }

        $this->info("Predicción de prueba creada para {$creadas} usuario(s) en la quiniela \"{$tipo}\".");
        $this->info('Ahora ve al admin y resuélvela de la forma normal, para probar el flujo real.');

        return 0;
    }
}