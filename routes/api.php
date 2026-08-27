<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LigaController;
use App\Http\Controllers\Api\V1\PartidoController;
use App\Http\Controllers\Api\V1\PronosticoController;
use App\Http\Controllers\Api\V1\JornadaController;
use App\Http\Controllers\Api\V1\ClasificacionController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

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
    Route::post('/profile/password', [\App\Http\Controllers\Api\V1\ProfileController::class, 'updatePassword']);
    Route::post('/profile/avatar', [\App\Http\Controllers\Api\V1\ProfileController::class, 'uploadAvatar']);
    Route::get('/clasificacion-liga', [\App\Http\Controllers\Api\V1\LaLigaStandingsController::class, 'index']);
    Route::get('/equipos/{equipo}/partidos', [\App\Http\Controllers\Api\V1\EquipoPartidosController::class, 'index']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::apiResource('equipos', \App\Http\Controllers\Api\V1\Admin\EquipoAdminController::class)->except('show');
        Route::apiResource('jugadores', \App\Http\Controllers\Api\V1\Admin\JugadorAdminController::class)->except('show');
        Route::apiResource('estadios', \App\Http\Controllers\Api\V1\Admin\EstadioAdminController::class)->except('show');
        Route::apiResource('arbitros', \App\Http\Controllers\Api\V1\Admin\ArbitroAdminController::class)->except('show');
        Route::apiResource('trofeos', \App\Http\Controllers\Api\V1\Admin\TrofeoAdminController::class)->except('show');
        Route::get('/usuarios', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'index']);
        Route::patch('/usuarios/{user}/rol', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'actualizarRol']);
        Route::get('/ligas', [\App\Http\Controllers\Api\V1\Admin\LigaAdminController::class, 'index']);
        Route::delete('/ligas/{liga}', [\App\Http\Controllers\Api\V1\Admin\LigaAdminController::class, 'destroy']);
        Route::get('/ligas/{liga}/dashboard', [\App\Http\Controllers\Api\V1\DashboardController::class, 'index']);
        Route::post('/usuarios', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'store']);
        Route::put('/usuarios/{user}', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'update']);
        Route::post('/usuarios/{user}/activar', [\App\Http\Controllers\Api\V1\Admin\UsuarioAdminController::class, 'activar']);
        Route::get('/calendario', [\App\Http\Controllers\Api\V1\Admin\CalendarioAdminController::class, 'index']);
        Route::put('/calendario/{partido}', [\App\Http\Controllers\Api\V1\Admin\CalendarioAdminController::class, 'update']);
    });
    });

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/verify-email/{id}/{hash}', function (\Illuminate\Foundation\Auth\EmailVerificationRequest $request) {
    $request->fulfill();
    return response()->json(['message' => 'Email verificado y cuenta activada.']);
    })->middleware(['auth:sanctum', 'signed'])->name('verification.verify');
});