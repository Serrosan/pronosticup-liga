<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Genera un .sql de TODA la base de datos para cargarlo en local con HeidiSQL.
 * Admin → Sistema → Copia para local.
 *
 * - Sin la tabla `migrations` (en local ya tienes la tuya; luego `migrate`).
 * - Sesiones, tokens, colas y caché: la tabla se crea, pero vacía.
 * - Opcional: correos falsos (menos superadmins) y contraseña "password"
 *   para todos, para que el archivo no lleve datos de acceso reales.
 * - Una sola foto de la base (transacción con instantánea): lo que se
 *   escriba mientras se genera no la deja a medias.
 *
 * Solo LEE de la base de datos.
 */
class CopiaParaLocalService
{
    /** Tablas que no van en la copia. */
    public const SIN_TABLA = ['migrations'];

    /** Tablas que van vacías: solo su estructura. */
    public const SIN_DATOS = [
        'sessions', 'personal_access_tokens', 'password_reset_tokens',
        'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks',
    ];

    private const FILAS_POR_INSERT = 200;

    private ?string $hashPassword = null;

    /**
     * @param  callable(string):void  $escribir  recibe el .sql a trozos
     * @return array{tablas:int,filas:int}
     */
    public function generar(callable $escribir, bool $correosFalsos, bool $passwordDePrueba): array
    {
        $pdo = DB::connection()->getPdo();
        $tablas = $this->tablas();
        $filasTotales = 0;

        $escribir($this->cabecera($correosFalsos, $passwordDePrueba));

        // Foto coherente de toda la base. Solo si no hay ya una transacción
        // abierta (empezar otra la confirmaría sin querer, p. ej. en los tests).
        $instantanea = DB::transactionLevel() === 0;
        if ($instantanea) {
            DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            DB::statement('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        }

        try {
            foreach ($tablas as $tabla) {
                $crear = (array) DB::selectOne("SHOW CREATE TABLE `{$tabla}`");
                $escribir("\n-- ----------------------------------------------------------\n-- {$tabla}\n");
                $escribir("DROP TABLE IF EXISTS `{$tabla}`;\n".$crear['Create Table'].";\n");

                if (in_array($tabla, self::SIN_DATOS, true)) {
                    continue;
                }

                $lote = [];
                $columnas = null;

                foreach (DB::table($tabla)->cursor() as $fila) {
                    $fila = (array) $fila;
                    if ($tabla === 'users') {
                        $fila = $this->anonimizarUsuario($fila, $correosFalsos, $passwordDePrueba);
                    }
                    $columnas ??= '`'.implode('`, `', array_keys($fila)).'`';
                    $lote[] = '('.implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $fila)).')';
                    $filasTotales++;

                    if (count($lote) >= self::FILAS_POR_INSERT) {
                        $escribir("INSERT INTO `{$tabla}` ({$columnas}) VALUES\n".implode(",\n", $lote).";\n");
                        $lote = [];
                    }
                }

                if ($lote) {
                    $escribir("INSERT INTO `{$tabla}` ({$columnas}) VALUES\n".implode(",\n", $lote).";\n");
                }
            }
        } finally {
            if ($instantanea) {
                DB::statement('COMMIT');
            }
        }

        $escribir("\nSET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n-- Copia completa: ".count($tablas)." tablas, {$filasTotales} filas.\n");

        return ['tablas' => count($tablas), 'filas' => $filasTotales];
    }

    /** Tablas reales de la base (sin vistas), menos las que no van. */
    public function tablas(): array
    {
        return collect(DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))
            ->map(fn ($fila) => array_values((array) $fila)[0])
            ->reject(fn ($tabla) => in_array($tabla, self::SIN_TABLA, true))
            ->sort()
            ->values()
            ->all();
    }

    /** Una fila de `users` tal como va al archivo. Los superadmins conservan su correo. */
    public function anonimizarUsuario(array $fila, bool $correosFalsos, bool $passwordDePrueba): array
    {
        if ($correosFalsos && array_key_exists('email', $fila) && ! ($fila['es_superadmin'] ?? false)) {
            $fila['email'] = 'usuario'.$fila['id'].'@pronosticup.local';
        }

        if ($passwordDePrueba && array_key_exists('password', $fila)) {
            $fila['password'] = $this->hashPassword ??= Hash::make('password');
        }

        if (array_key_exists('remember_token', $fila)) {
            $fila['remember_token'] = null;
        }

        return $fila;
    }

    private function cabecera(bool $correosFalsos, bool $passwordDePrueba): string
    {
        $fecha = now()->format('d/m/Y H:i');
        $correos = $correosFalsos ? 'falsos (usuarioN@pronosticup.local), menos superadmins' : 'los reales';
        $passwords = $passwordDePrueba ? '"password" para todos' : 'las reales (cifradas)';

        return <<<SQL
-- =============================================================
--  PronostiCup · copia para cargar en LOCAL · {$fecha}
--  Correos: {$correos}
--  Contraseñas: {$passwords}
--  Sin la tabla migrations. Sesiones, tokens, colas y caché van vacías.
--  Después de cargarla:  ./vendor/bin/sail artisan migrate
-- =============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

SQL;
    }
}
