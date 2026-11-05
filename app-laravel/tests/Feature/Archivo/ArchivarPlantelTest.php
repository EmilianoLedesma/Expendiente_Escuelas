<?php

namespace Tests\Feature\Archivo;

use App\Application\Archivo\ArchivarPlantel;
use App\Application\Archivo\ArchivarTramite;
use App\Application\Archivo\RestaurarPlantel;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Models\BitacoraAccion;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Scopes\EscuelaNoArchivada;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivarPlantelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolesSeeder)->run();
        $this->admin = User::factory()->create()->assignRole(RolesSedeq::ADMIN);
    }

    private function plantel(): Plantel
    {
        return Plantel::create(['calle' => 'Hidalgo', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
    }

    private function escuela(Plantel $p): Escuela
    {
        $s = Solicitante::factory()->create(['user_id' => User::factory()->create()->id]);

        return Escuela::create(['plantel_id' => $p->id, 'solicitante_id' => $s->id]);
    }

    private function sinScope(int $id): Escuela
    {
        return Escuela::withoutGlobalScope(EscuelaNoArchivada::class)->findOrFail($id);
    }

    public function test_archiva_todos_los_tramites_activos_con_un_lote_y_deja_traza(): void
    {
        $p = $this->plantel();
        $a = $this->escuela($p);
        $b = $this->escuela($p);
        $otra = $this->escuela($this->plantel());

        app(ArchivarPlantel::class)->ejecutar($p->id, $this->admin->id, 'Cierre');

        $this->assertNotNull($this->sinScope($a->id)->archivado_at);
        $this->assertNotNull($this->sinScope($b->id)->archivado_at);
        $this->assertSame($this->sinScope($a->id)->archivado_lote, $this->sinScope($b->id)->archivado_lote);
        $this->assertNull($this->sinScope($otra->id)->archivado_at);
        $fila = BitacoraAccion::where('accion', 'archivar_plantel')->firstOrFail();
        $this->assertSame($p->id, (int) $fila->objeto_id);
        $this->assertSame(2, $fila->detalle['tramites']);
    }

    public function test_sin_tramites_activos_se_rechaza(): void
    {
        $p = $this->plantel();
        $e = $this->escuela($p);
        app(ArchivarTramite::class)->ejecutar($e->id, $this->admin->id);

        $this->expectException(PrecondicionIncumplida::class);
        app(ArchivarPlantel::class)->ejecutar($p->id, $this->admin->id);
    }

    public function test_solo_admin(): void
    {
        $p = $this->plantel();
        $this->escuela($p);
        $revisor = User::factory()->create()->assignRole(RolesSedeq::REVISOR);

        $this->expectException(PrecondicionIncumplida::class);
        app(ArchivarPlantel::class)->ejecutar($p->id, $revisor->id);
    }

    public function test_restaurar_devuelve_solo_los_del_lote_del_plantel(): void
    {
        $p = $this->plantel();
        $previa = $this->escuela($p);
        app(ArchivarTramite::class)->ejecutar($previa->id, $this->admin->id, 'individual');
        $a = $this->escuela($p);
        app(ArchivarPlantel::class)->ejecutar($p->id, $this->admin->id);

        app(RestaurarPlantel::class)->ejecutar($this->admin->id, $p->id);

        $this->assertNull($this->sinScope($a->id)->archivado_at);
        $this->assertNotNull($this->sinScope($previa->id)->archivado_at);
        $this->assertSame(1, BitacoraAccion::where('accion', 'restaurar_plantel')->count());
    }

    public function test_restaurar_sin_archivo_previo_se_rechaza(): void
    {
        $p = $this->plantel();
        $this->escuela($p);

        $this->expectException(PrecondicionIncumplida::class);
        app(RestaurarPlantel::class)->ejecutar($this->admin->id, $p->id);
    }
}
