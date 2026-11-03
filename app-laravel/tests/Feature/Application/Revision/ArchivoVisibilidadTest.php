<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\AccesoSedeq;
use App\Application\Revision\AsignarTramite;
use App\Application\Revision\ListarColaDelRevisor;
use App\Application\Revision\ListarTablero;
use App\Application\Tramite\ListarTramitesDelSolicitante;
use App\Models\AsignacionRevision;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Un trámite archivado desaparece de todas las lecturas SEDEQ y del dueño; un revisor archivado no se asigna ni se cuenta. */
class ArchivoVisibilidadTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private User $sup;

    private User $revisor;

    private User $dueno;

    private int $solicitanteId;

    private EscuelaNivel $nivel;

    private int $escuelaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sup = $this->supervisor();
        $this->revisor = $this->revisor();
        $this->nivel = $this->nivelEnRevision();
        $this->escuelaId = $this->nivel->escuela_id;
        $solicitante = $this->nivel->escuela->solicitante;
        $this->solicitanteId = $solicitante->id;
        $this->dueno = $solicitante->user;
        AsignacionRevision::create(['escuela_nivel_id' => $this->nivel->id, 'revisor_id' => $this->revisor->id, 'asignado_por' => $this->sup->id, 'asignado_at' => now()]);
    }

    private function archivarEscuela(): void
    {
        DB::table('escuelas')->where('id', $this->escuelaId)->update(['archivado_at' => now()]);
    }

    public function test_el_tablero_y_la_cola_omiten_el_tramite_archivado(): void
    {
        $this->assertCount(1, ListarTablero::filas(null));
        $this->archivarEscuela();

        $this->assertSame([], ListarTablero::filas(null));
        $this->assertSame([], app(ListarColaDelRevisor::class)->ejecutar($this->revisor->id));
    }

    public function test_el_acceso_sedeq_y_la_ruta_niegan_el_tramite_archivado(): void
    {
        $this->archivarEscuela();
        $acceso = app(AccesoSedeq::class);

        $this->assertFalse($acceso->puedeVerEscuela($this->sup, $this->escuelaId));
        $this->assertFalse($acceso->puedeVerEscuela($this->revisor, $this->escuelaId));
        $this->actingAs($this->sup)->get(route('sedeq.tramite', ['escuela' => $this->escuelaId]))->assertNotFound();
        $this->actingAs($this->revisor)->get(route('sedeq.tramite', ['escuela' => $this->escuelaId]))->assertNotFound();
    }

    public function test_el_dueno_no_ve_su_tramite_archivado(): void
    {
        $this->archivarEscuela();

        $this->assertSame([], app(ListarTramitesDelSolicitante::class)->ejecutar($this->solicitanteId));
        $this->actingAs($this->dueno)->get(route('tramite.resumen', ['escuela' => $this->escuelaId]))->assertNotFound();
    }

    public function test_un_revisor_archivado_no_aparece_en_las_barras_ni_se_le_asigna(): void
    {
        $otro = $this->nivelEnRevision();
        DB::table('users')->where('id', $this->revisor->id)->update(['archivado_at' => now()]);

        $ids = array_map(fn ($b) => $b->revisorId, app(ListarTablero::class)->ejecutar($this->sup->id)['revisores']);
        $this->assertNotContains($this->revisor->id, $ids);

        $this->expectException(PrecondicionIncumplida::class);
        app(AsignarTramite::class)->ejecutar($this->sup->id, $otro->id, $this->revisor->id);
    }
}
