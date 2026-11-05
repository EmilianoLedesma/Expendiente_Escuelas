<?php

namespace Tests\Feature\Admin;

use App\Application\Revision\RolesSedeq;
use App\Filament\Pages\SaludSistema;
use App\Models\BitacoraAccion;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SaludSistemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_ve_los_cinco_chequeos(): void
    {
        (new RolesSeeder)->run();
        Storage::fake('documentos');
        $this->actingAs(User::factory()->create()->assignRole(RolesSedeq::ADMIN));

        Livewire::test(SaludSistema::class)->assertOk()
            ->assertSee('Base de datos')->assertSee('Cola de correos')->assertSee('Correo')
            ->assertSee('Disco de documentos')->assertSee('Migraciones');
    }

    public function test_quien_no_es_admin_no_entra_al_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/salud')->assertForbidden();
    }

    public function test_los_botones_de_la_cola_operan_y_notifican(): void
    {
        (new RolesSeeder)->run();
        Storage::fake('documentos');
        config(['queue.default' => 'database']);
        $admin = User::factory()->create()->assignRole(RolesSedeq::ADMIN);
        $this->actingAs($admin);
        DB::table('failed_jobs')->insert(['uuid' => 'u-1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x']);

        Livewire::test(SaludSistema::class)
            ->assertSee('Qué hacer')->assertSee('Reintentar fallidos')->assertSee('Enviar correo de prueba')
            ->callAction('descartarFallidos')->assertNotified();

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, BitacoraAccion::where('accion', 'descartar_fallidos')->count());
    }
}
