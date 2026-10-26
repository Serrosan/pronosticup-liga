<?php

namespace Tests\Feature;

use App\Jobs\Partidos\SincronizarPartidoLaligaJob;
use Tests\TestCase;

/** El scraper elige el enlace del partido correcto en la página de resultados de LaLiga. */
class EnlacePartidoLaligaTest extends TestCase
{
    /** Los enlaces reales de la jornada 10, más uno inventado para un Alavés - Deportivo. */
    private const ENLACES = [
        '/partido/temporada-2026-2027-laliga-ea-sports-athletic-club-getafe-cf-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-atletico-de-madrid-rc-deportivo-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-deportivo-alaves-malaga-cf-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-fc-barcelona-real-madrid-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-r-racing-club-rcd-espanyol-de-barcelona-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-rayo-vallecano-elche-cf-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-rc-celta-real-betis-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-real-sociedad-levante-ud-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-sevilla-fc-ca-osasuna-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-valencia-cf-villarreal-cf-10',
        '/partido/temporada-2026-2027-laliga-ea-sports-deportivo-alaves-rc-deportivo-20',
    ];

    private function enlace(string $local, string $visitante): ?string
    {
        $metodo = new \ReflectionMethod(SincronizarPartidoLaligaJob::class, 'encontrarLinkDelPartido');

        return $metodo->invoke(new SincronizarPartidoLaligaJob(1), self::ENLACES, $local, $visitante);
    }

    public function test_celta_betis_no_se_confunde_con_el_clasico(): void
    {
        $this->assertStringEndsWith('rc-celta-real-betis-10', $this->enlace('Real Club Celta de Vigo', 'Real Betis Balompié'));
    }

    public function test_cada_partido_de_la_jornada_encuentra_el_suyo(): void
    {
        $casos = [
            ['Fútbol Club Barcelona', 'Real Madrid Club de Fútbol', 'fc-barcelona-real-madrid-10'],
            ['Real Sociedad de Fútbol', 'Levante Unión Deportiva', 'real-sociedad-levante-ud-10'],
            ['Real Racing Club de Santander', 'RCD Espanyol de Barcelona', 'r-racing-club-rcd-espanyol-de-barcelona-10'],
            ['Club Atlético de Madrid', 'RC Deportivo de A Coruña', 'atletico-de-madrid-rc-deportivo-10'],
            ['Deportivo Alavés', 'Málaga Club de Fútbol', 'deportivo-alaves-malaga-cf-10'],
            ['Athletic Club', 'Getafe Club de Fútbol', 'athletic-club-getafe-cf-10'],
            ['Valencia Club de Fútbol', 'Villarreal Club de Fútbol', 'valencia-cf-villarreal-cf-10'],
            ['Sevilla Fútbol Club', 'Club Atlético Osasuna', 'sevilla-fc-ca-osasuna-10'],
        ];

        foreach ($casos as [$local, $visitante, $esperado]) {
            $this->assertStringEndsWith($esperado, (string) $this->enlace($local, $visitante), "$local - $visitante");
        }
    }

    public function test_si_las_palabras_distintas_no_bastan_usa_todas(): void
    {
        // "Deportivo" lo comparten; "RC Deportivo" no lleva "Coruña" en el enlace.
        $this->assertStringEndsWith('deportivo-alaves-rc-deportivo-20', (string) $this->enlace('Deportivo Alavés', 'RC Deportivo de A Coruña'));
    }
}
