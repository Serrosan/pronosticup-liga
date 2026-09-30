<?php

namespace App\Console\Commands;

use App\Models\Temporada;
use App\Services\ImportadorPartidoDetalle;
use Illuminate\Console\Command;

class ImportarPartidoDetalle extends Command
{
    protected $signature = 'liga:importar-partido-detalle {archivo : Ruta al JSON con alineaciones/estadísticas/eventos de la jornada}';

    protected $description = 'Importa alineaciones, estadísticas y eventos de partido desde un JSON capturado de LaLiga.com';

    public function handle(ImportadorPartidoDetalle $importador): int
    {
        $ruta = $this->argument('archivo');

        if (! file_exists($ruta)) {
            $this->error("No existe el archivo: {$ruta}");
            return self::FAILURE;
        }

        $partidos = json_decode(file_get_contents($ruta), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('El archivo no es un JSON válido: '.json_last_error_msg());
            return self::FAILURE;
        }

        // Se asume la temporada activa más reciente — ajusta aquí si algún día
        // convives con 2 temporadas superpuestas.
        $idTemporada = Temporada::orderByDesc('fecha_inicio')->value('id');

        $totalAvisos = 0;
        $lineasParaElLog = [];
        $jornadaEtiqueta = $partidos[0]['jornada'] ?? '?';

        foreach ($partidos as $partido) {
            $etiqueta = "{$partido['equipo_local']} vs {$partido['equipo_visitante']} (J{$partido['jornada']})";
            $resultado = $importador->importar($partido, $idTemporada);

            if (! $resultado['ok']) {
                $this->error("✗ {$etiqueta}");
            } else {
                $this->info("✓ {$etiqueta}");
            }

            foreach ($resultado['avisos'] as $aviso) {
                $this->line("    ⚠️  {$aviso}");
                $lineasParaElLog[] = "[{$etiqueta}] {$aviso}";
            }
            $totalAvisos += count($resultado['avisos']);
        }

        $this->newLine();
        $this->info(count($partidos).' partido(s) procesados.');

        if ($totalAvisos > 0) {
            $this->warn("{$totalAvisos} aviso(s) — revisa las líneas ⚠️ de arriba, seguramente jugadores o goles que no se pudieron emparejar del todo.");

            // Se guardan también en un archivo, para poder revisarlos con calma
            // más tarde sin depender de lo que quede en el scroll de la terminal.
            $rutaLog = storage_path('app/scraper/avisos.log');
            if (! is_dir(dirname($rutaLog))) {
                mkdir(dirname($rutaLog), 0755, true);
            }
            $cabecera = '=== '.now()->format('Y-m-d H:i:s')." — jornada {$jornadaEtiqueta} (".count($partidos).' partidos) ==='.PHP_EOL;
            file_put_contents($rutaLog, $cabecera.implode(PHP_EOL, $lineasParaElLog).PHP_EOL.PHP_EOL, FILE_APPEND);

            $this->line("Guardados también en: storage/app/scraper/avisos.log");
        }

        return self::SUCCESS;
    }
}