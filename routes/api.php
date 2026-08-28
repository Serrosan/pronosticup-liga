<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LigaController;
use App\Http\Controllers\Api\V1\PartidoController;
use App\Http\Controllers\Api\V1\PronosticoController;
use App\Http\Controllers\Api\V1\JornadaController;
use App\Http\Controllers\Api\V1\ClasificacionController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/ligas', [App\Http\Controllers\Api\V1\LigaController::class, 'index']);
    Route::post('/ligas', [App\Http\Controllers\Api\V1\LigaController::class, 'store']);
    Route::post('/ligas/unirse', [App\Http\Controllers\Api\V1\LigaController::class, 'join']);
    Route::get('/ligas/{liga}', [App\Http\Controllers\Api\V1\LigaController::class, 'show']);
    Route::get('/jornadas/{jornada}/partidos', [PartidoController::class, 'porJornada']);
    Route::post('/pronosticos', [PronosticoController::class, 'store']);
    Route::get('/pronosticos', [PronosticoController::class, 'todos']);
    Route::get('/jornadas/{jornada}/pronosticos', [PronosticoController::class, 'misPronosticos']);
    Route::get('/clasificacion', [\App\Http\Controllers\Api\V1\ClasificacionController::class, 'index']);
    Route::get('/dashboard', [\App\Http\Controllers\Api\V1\DashboardController::class, 'index']);
    Route::post('/jornadas/{jornada}/cerrar', [\App\Http\Controllers\Api\V1\JornadaController::class, 'cerrar']);
    Route::patch('/liga-activa', [\App\Http\Controllers\Api\V1\LigaActivaController::class, 'set']);
    Route::patch('/profile', [\App\Http\Controllers\Api\V1\ProfileController::class, 'update']);
    Route::post('/chat/subir-imagen', [\App\Http\Controllers\Api\V1\ChatAdjuntoController::class, 'subirImagen']);
    Route::post('/chat/subir-audio', [\App\Http\Controllers\Api\V1\ChatAdjuntoController::class, 'subirAudio']);
    Route::post('/profile/password', [\App\Http\Controllers\Api\V1\ProfileController::class, 'updatePassword']);
    Route::post('/profile/avatar', [\App\Http\Controllers\Api\V1\ProfileController::class, 'uploadAvatar']);
    Route::get('/clasificacion-liga', [\App\Http\Controllers\Api\V1\LaLigaStandingsController::class, 'index']);
    Route::get('/chat', [\App\Http\Controllers\Api\V1\ChatController::class, 'index']);
    Route::post('/chat', [\App\Http\Controllers\Api\V1\ChatController::class, 'store']);
    Route::post('/chat/{mensajeChat}/reaccionar', [\App\Http\Controllers\Api\V1\ChatController::class, 'reaccionar']);
    Route::get('/calendario', [\App\Http\Controllers\Api\V1\CalendarioController::class, 'mes']);
    Route::get('/equipos/{equipo}/partidos', [\App\Http\Controllers\Api\V1\EquipoPartidosController::class, 'index']);
    Route::get('/partidos/{partido}', [PartidoController::class, 'show']);
    Route::get('/clasificacion/usuarios/{usuario}/detalle', [\App\Http\Controllers\Api\V1\ClasificacionController::class, 'detalle']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/equipos/{equipo}', [\App\Http\Controllers\Api\V1\Admin\EquipoAdminController::class, 'show']);
        Route::apiResource('equipos', \App\Http\Controllers\Api\V1\Admin\EquipoAdminController::class)->except('show');
        Route::get('/jugadores/{jugador}', [\App\Http\Controllers\Api\V1\Admin\JugadorAdminController::class, 'show']);
        Route::apiResource('jugadores', \App\Http\Controllers\Api\V1\Admin\JugadorAdminController::class)
    ->except('show')
    ->parameters(['jugadores' => 'jugador']);
        Route::get('/arbitros/{arbitro}', [\App\Http\Controllers\Api\V1\Admin\ArbitroAdminController::class, 'show']);
        Route::get('/novedades', [\App\Http\Controllers\Api\V1\Admin\NovedadAdminController::class, 'index']);
        Route::post('/novedades', [\App\Http\Controllers\Api\V1\Admin\NovedadAdminController::class, 'store']);
        Route::get('/novedades/{novedad}', [\App\Http\Controllers\Api\V1\Admin\NovedadAdminController::class, 'show']);
        Route::put('/novedades/{novedad}', [\App\Http\Controllers\Api\V1\Admin\NovedadAdminController::class, 'update']);
        Route::delete('/novedades/{novedad}', [\App\Http\Controllers\Api\V1\Admin\NovedadAdminController::class, 'destroy']);
        Route::get('/trofeos/{trofeo}', [\App\Http\Controllers\Api\V1\Admin\TrofeoAdminController::class, 'show']);
        Route::get('/estadios/{estadio}', [\App\Http\Controllers\Api\V1\Admin\EstadioAdminController::class, 'show']);
        Route::post('/jugadores/{jugador}/fichar', [\App\Http\Controllers\Api\V1\Admin\FichajeAdminController::class, 'fichar']);
        Route::get('/jugadores/{jugador}/historial', [\App\Http\Controllers\Api\V1\Admin\FichajeAdminController::class, 'historial']);
        Route::post('/jugadores/{jugador}/dar-de-baja', [\App\Http\Controllers\Api\V1\Admin\FichajeAdminController::class, 'darDeBaja']);
        Route::post('/jugadores/{jugador}/reactivar', [\App\Http\Controllers\Api\V1\Admin\FichajeAdminController::class, 'reactivar']);
        Route::apiResource('estadios', \App\Http\Controllers\Api\V1\Admin\EstadioAdminController::class)->except('show');
        Route::apiResource('arbitros', \App\Http\Controllers\Api\V1\Admin\ArbitroAdminController::class)->except('show');
        Route::post('/subir-imagen', [\App\Http\Controllers\Api\V1\Admin\ImagenAdminController::class, 'subir']);
        Route::apiResource('trofeos', \App\Http\Controllers\Api\V1\Admin\TrofeoAdminController::class)->except('show');
        Route::get('/usuarios', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'index']);
        Route::patch('/usuarios/{user}/rol', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'actualizarRol']);
        Route::get('/ligas', [\App\Http\Controllers\Api\V1\Admin\LigaAdminController::class, 'index']);
        Route::get('/ligas/{liga}', [\App\Http\Controllers\Api\V1\Admin\LigaAdminController::class, 'show']);
        Route::put('/ligas/{liga}', [\App\Http\Controllers\Api\V1\Admin\LigaAdminController::class, 'update']);
        Route::delete('/ligas/{liga}', [\App\Http\Controllers\Api\V1\Admin\LigaAdminController::class, 'destroy']);
        Route::get('/eventos-calendario', [\App\Http\Controllers\Api\V1\Admin\EventoCalendarioAdminController::class, 'index']);
        Route::post('/eventos-calendario', [\App\Http\Controllers\Api\V1\Admin\EventoCalendarioAdminController::class, 'store']);
        Route::get('/eventos-calendario/{eventoCalendario}', [\App\Http\Controllers\Api\V1\Admin\EventoCalendarioAdminController::class, 'show']);
        Route::put('/eventos-calendario/{eventoCalendario}', [\App\Http\Controllers\Api\V1\Admin\EventoCalendarioAdminController::class, 'update']);
        Route::delete('/eventos-calendario/{eventoCalendario}', [\App\Http\Controllers\Api\V1\Admin\EventoCalendarioAdminController::class, 'destroy']);
        Route::post('/usuarios', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'store']);
        Route::put('/usuarios/{user}', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'update']);
        Route::post('/usuarios/{user}/activar', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'activar']);
        Route::get('/calendario', [\App\Http\Controllers\Api\V1\Admin\CalendarioAdminController::class, 'index']);
        Route::put('/calendario/{partido}', [\App\Http\Controllers\Api\V1\Admin\CalendarioAdminController::class, 'update']);
        Route::post('/eventos-partido/interpretar', [\App\Http\Controllers\Api\V1\Admin\EventoPartidoAdminController::class, 'interpretar']);
        Route::post('/eventos-partido/guardar', [\App\Http\Controllers\Api\V1\Admin\EventoPartidoAdminController::class, 'guardar']);
    });
    });

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('/verify-email/{id}/{hash}', function (\Illuminate\Foundation\Auth\EmailVerificationRequest $request) {
    $request->fulfill();
    return response()->json(['message' => 'Email verificado y cuenta activada.']);
    })->middleware(['auth:sanctum', 'signed'])->name('verification.verify');
});