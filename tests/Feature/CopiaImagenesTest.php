<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CopiaImagenesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

/**
 * Copia de imágenes para local: solo superadmin, solo carpetas que existen.
 */
class CopiaImagenesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::disk('public')->put('avatars/ana.png', 'imagen-de-ana');
        Storage::disk('public')->put('avatars/juan.png', 'imagen-de-juan');
        Storage::disk('public')->put('chat/audio.mp4', 'audio');
        Storage::disk('public')->put('.gitignore', '*');

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['es_superadmin' => true])->save();
    }

    public function test_lista_las_carpetas_con_cuantos_archivos_tienen(): void
    {
        $carpetas = collect(app(CopiaImagenesService::class)->carpetas())->keyBy('carpeta');

        $this->assertSame(['avatars', 'chat'], $carpetas->keys()->all(), 'El .gitignore suelto no cuenta');
        $this->assertSame(2, $carpetas['avatars']['archivos']);
        $this->assertSame(strlen('imagen-de-ana') + strlen('imagen-de-juan'), $carpetas['avatars']['bytes']);
    }

    public function test_el_zip_lleva_solo_las_carpetas_elegidas(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Sin la extensión zip de PHP.');
        }

        $ruta = app(CopiaImagenesService::class)->generarZip(['avatars', '../../.env', 'no-existe']);

        $zip = new ZipArchive();
        $zip->open($ruta);
        $nombres = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombres[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($ruta);

        sort($nombres);
        $this->assertSame(['avatars/ana.png', 'avatars/juan.png'], $nombres);
    }

    public function test_un_usuario_normal_no_puede_ni_ver_las_carpetas(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/copia-local/imagenes')->assertForbidden();
        $this->postJson('/api/v1/admin/copia-local/imagenes', ['carpetas' => ['avatars']])->assertForbidden();
    }

    public function test_pedir_carpetas_que_no_existen_da_error_claro(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/copia-local/imagenes')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/admin/copia-local/imagenes', ['carpetas' => ['../secretos']])
            ->assertStatus(422);
    }
}
