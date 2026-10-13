<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CopiaParaLocalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Copia para local: solo superadmin, y por defecto sin datos de acceso reales.
 */
class CopiaParaLocalTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_correos_se_cambian_menos_el_del_superadmin_y_la_contrasena_es_password(): void
    {
        $servicio = app(CopiaParaLocalService::class);

        $jugador = $servicio->anonimizarUsuario(['id' => 7, 'email' => 'juan@real.es', 'password' => 'x', 'remember_token' => 'abc', 'es_superadmin' => 0], true, true);
        $admin = $servicio->anonimizarUsuario(['id' => 1, 'email' => 'yo@real.es', 'password' => 'x', 'es_superadmin' => 1], true, true);
        $sinTocar = $servicio->anonimizarUsuario(['id' => 7, 'email' => 'juan@real.es', 'password' => 'x'], false, false);

        $this->assertSame('usuario7@pronosticup.local', $jugador['email']);
        $this->assertTrue(Hash::check('password', $jugador['password']));
        $this->assertNull($jugador['remember_token']);
        $this->assertSame('yo@real.es', $admin['email']);
        $this->assertSame('juan@real.es', $sinTocar['email']);
        $this->assertSame('x', $sinTocar['password']);
    }

    public function test_un_usuario_normal_no_puede_descargarla(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/copia-local', ['correos_falsos' => true, 'password_de_prueba' => true])
            ->assertForbidden();
    }

    public function test_el_superadmin_descarga_un_sql_completo_y_anonimizado(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('La copia usa SHOW CREATE TABLE: solo se prueba con MySQL.');
        }

        $admin = User::factory()->create(['email' => 'admin@real.es']);
        $admin->forceFill(['es_superadmin' => true])->save();
        User::factory()->create(['email' => 'juan@real.es']);
        Sanctum::actingAs($admin);

        $respuesta = $this->post('/api/v1/admin/copia-local', ['correos_falsos' => true, 'password_de_prueba' => true]);
        $respuesta->assertOk();
        $sql = $respuesta->streamedContent();

        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringNotContainsString('CREATE TABLE `migrations`', $sql);
        $this->assertStringContainsString('admin@real.es', $sql);
        $this->assertStringNotContainsString('juan@real.es', $sql);
        $this->assertStringContainsString('@pronosticup.local', $sql);
        $this->assertStringContainsString('-- Copia completa:', $sql);
    }
}
