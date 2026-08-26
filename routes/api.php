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
    Route::get('/ligas/{liga}/jornadas/{jornada}/partidos', [PartidoController::class, 'porJornada']);
    Route::post('/ligas/{liga}/pronosticos', [PronosticoController::class, 'store']);
    Route::get('/ligas/{liga}/jornadas/{jornada}/pronosticos', [PronosticoController::class, 'misPronosticos']);
    Route::post('/ligas/{liga}/jornadas/{jornada}/cerrar', [JornadaController::class, 'cerrar']);
    Route::get('/ligas/{liga}/clasificacion', [ClasificacionController::class, 'index']);
    });

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/verify-email/{id}/{hash}', function (\Illuminate\Foundation\Auth\EmailVerificationRequest $request) {
    $request->fulfill();
    return response()->json(['message' => 'Email verificado y cuenta activada.']);
    })->middleware(['auth:sanctum', 'signed'])->name('verification.verify');
});