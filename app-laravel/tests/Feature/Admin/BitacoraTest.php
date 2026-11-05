<?php

namespace Tests\Feature\Admin;

use App\Application\Revision\RolesSedeq;
use App\Filament\Resources\Bitacora\BitacoraResource;
use App\Filament\Resources\Bitacora\Pages\ListBitacora;
use App\Models\BitacoraAccion;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use App\Models\TernaNombre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->admin = User::factory()->create(['name' => 'Ana Admin'])->assignRole(RolesSedeq::ADMIN);
        $this->actingAs($this->admin);
    }

    private function fila(string $accion, string $tipo, int $id, ?User $actor = null, string $cuando = 'now', ?string $motivo = null): BitacoraAccion
    {
        return BitacoraAccion::create([
            'actor_id' => ($actor ?? $this->admin)->id, 'accion' => $accion, 'objeto_tipo' => $tipo, 'objeto_id' => $id,
            'motivo' => $motivo, 'detalle' => ['trabajos' => 3, 'lote' => 'x'], 'created_at' => now()->modify($cuando),
        ]);
    }

    public function test_lista_todo_con_texto_en_palabras(): void
    {
        $a = $this->fila('archivar_tramite', 'escuela', 4, motivo: 'Duplicado');
        $b = $this->fila('reintentar_fallidos', 'sistema', 0);

        Livewire::test(ListBitacora::class)->assertCanSeeTableRecords([$a, $b])
            ->assertSee('Archivó el trámite')->assertSee('Trámite · #4')->assertSee('Duplicado')
            ->assertSee('Ana Admin')->assertSee('Sistema')->assertSee('trabajos: 3');
    }

    public function test_resuelve_tramite_cuenta_y_plantel_a_su_nombre(): void
    {
        $dueno = User::factory()->create(['name' => 'Dora Dueña']);
        $plantel = Plantel::create(['calle' => 'Hidalgo', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create(['user_id' => $dueno->id])->id, 'nombre_aprobado' => 'Colegio Sol']);
        $sinNombre = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $escuela->solicitante_id]);
        TernaNombre::create(['escuela_id' => $sinNombre->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Luna']);
        $this->fila('archivar_tramite', 'escuela', $escuela->id);
        $this->fila('archivar_tramite', 'escuela', $sinNombre->id);
        $this->fila('archivar_cuenta', 'user', $dueno->id);
        $this->fila('archivar_plantel', 'plantel', $plantel->id);
        $this->fila('purgar_tramite', 'escuela', 999999);

        Livewire::test(ListBitacora::class)
            ->assertSee('Colegio Sol')->assertSee('Colegio Luna')->assertSee('Dora Dueña')
            ->assertSee('Hidalgo 12, Centro')->assertSee('Ya no existe');
    }

    public function test_filtra_por_accion_actor_y_tipo(): void
    {
        $otro = User::factory()->create(['name' => 'Beto Admin'])->assignRole(RolesSedeq::ADMIN);
        $a = $this->fila('archivar_tramite', 'escuela', 4);
        $b = $this->fila('cambiar_rol', 'user', 9, $otro);

        Livewire::test(ListBitacora::class)
            ->filterTable('accion', 'cambiar_rol')->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a])
            ->removeTableFilter('accion')
            ->filterTable('actor_id', $this->admin->id)->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b])
            ->removeTableFilter('actor_id')
            ->filterTable('objeto_tipo', 'user')->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
    }

    public function test_filtra_por_rango_de_fechas(): void
    {
        $viejo = $this->fila('archivar_tramite', 'escuela', 1, cuando: '-10 days');
        $nuevo = $this->fila('archivar_tramite', 'escuela', 2);

        Livewire::test(ListBitacora::class)
            ->filterTable('fecha', ['desde' => now()->subDays(2)->toDateString(), 'hasta' => null])
            ->assertCanSeeTableRecords([$nuevo])->assertCanNotSeeTableRecords([$viejo]);
    }

    public function test_es_de_solo_lectura(): void
    {
        $this->assertFalse(BitacoraResource::canCreate());
        $this->assertArrayNotHasKey('create', BitacoraResource::getPages());
        $this->assertArrayNotHasKey('edit', BitacoraResource::getPages());
    }
}
