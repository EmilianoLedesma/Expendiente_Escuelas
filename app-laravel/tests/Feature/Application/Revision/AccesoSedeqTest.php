<?php

namespace Tests\Feature\Application\Revision;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Revision\AccesoSedeq;
use App\Application\Revision\ConsultarTramiteSedeq;
use App\Models\AsignacionRevision;
use App\Models\EscuelaNivel;
use App\Models\User;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Lectura SEDEQ (Tarea 6): un revisor ve solo su nivel (y la escuela); un supervisor, todo lo que no está en captura. */
class AccesoSedeqTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private EscuelaNivel $nivelA;

    private EscuelaNivel $nivelB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nivelA = $this->nivelEnRevision();
        $this->nivelB = $this->nivelEn($this->nivelA->escuela, 'secundaria', 'en_revision');
    }

    private function asignar(User $revisor, EscuelaNivel $nivel): void
    {
        AsignacionRevision::create(['escuela_nivel_id' => $nivel->id, 'revisor_id' => $revisor->id, 'asignado_por' => $this->supervisor()->id, 'asignado_at' => now()]);
    }

    public function test_el_revisor_ve_la_escuela_y_su_nivel_pero_no_el_nivel_hermano(): void
    {
        $revisor = $this->revisor();
        $this->asignar($revisor, $this->nivelA);
        $acceso = app(AccesoSedeq::class);

        $this->assertTrue($acceso->puedeVerEscuela($revisor, $this->nivelA->escuela_id));
        $this->assertTrue($acceso->puedeVerNivel($revisor, $this->nivelA->id));
        $this->assertFalse($acceso->puedeVerNivel($revisor, $this->nivelB->id));
    }

    public function test_el_supervisor_no_ve_escuelas_ni_niveles_en_captura(): void
    {
        $supervisor = $this->supervisor();
        $borrador = $this->nivelEnRevision('en_captura');
        $acceso = app(AccesoSedeq::class);

        $this->assertFalse($acceso->puedeVerEscuela($supervisor, $borrador->escuela_id));
        $this->assertFalse($acceso->puedeVerNivel($supervisor, $borrador->id));
        $this->assertTrue($acceso->puedeVerEscuela($supervisor, $this->nivelA->escuela_id));
        $this->assertTrue($acceso->puedeVerNivel($supervisor, $this->nivelB->id));
    }

    public function test_un_nivel_asignado_que_vuelve_a_captura_deja_de_ser_visible(): void
    {
        $revisor = $this->revisor();
        $borrador = $this->nivelEnRevision('en_captura');
        $this->asignar($revisor, $borrador);
        $acceso = app(AccesoSedeq::class);

        $this->assertFalse($acceso->puedeVerEscuela($revisor, $borrador->escuela_id));
        $this->assertFalse($acceso->puedeVerNivel($revisor, $borrador->id));
    }

    public function test_un_usuario_sin_rol_sedeq_no_tiene_acceso_sedeq(): void
    {
        $acceso = app(AccesoSedeq::class);
        $usuario = User::factory()->create();

        $this->assertFalse($acceso->puedeVerEscuela($usuario, $this->nivelA->escuela_id));
        $this->assertFalse($acceso->puedeVerNivel($usuario, $this->nivelA->id));
    }

    public function test_descargas_del_revisor_asignado_escuela_si_nivel_hermano_no(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuelaId = $this->nivelA->escuela_id;
        // Las Actions de captura rechazan un trámite enviado: se captura en borrador y luego se envía.
        $enCaptura = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');
        $enRevision = DB::table('estados_expediente')->where('clave', 'en_revision')->value('id');
        EscuelaNivel::where('escuela_id', $escuelaId)->update(['estado_id' => $enCaptura]);
        (new RegistrarResponsableLegal)->ejecutar($escuelaId, new DatosResponsableLegal(tipoPersona: 'fisica', domicilioNotificaciones: 'Calle 1', nombre: 'Juana Pérez'));
        (new RegistrarDocumento)->ejecutar($escuelaId, 'ine', UploadedFile::fake()->createWithContent('ine.pdf', 'CONTENIDO-INE'), new DatosDocumento);
        EscuelaNivel::where('escuela_id', $escuelaId)->update(['estado_id' => $enRevision]);
        $revisor = $this->revisor();
        $this->asignar($revisor, $this->nivelA);
        $this->actingAs($revisor);

        $escuela = $this->get(route('tramite.paso2-documentos.descargar', ['escuela' => $escuelaId, 'clave' => 'ine']));
        $escuela->assertOk();
        $this->assertSame('CONTENIDO-INE', $escuela->streamedContent());

        // Su nivel pasa la autorización (no hay archivo: 404); el hermano, 403.
        $this->get(route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $this->nivelA->id, 'clave' => 'formato_solicitud']))->assertNotFound();
        $this->get(route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $this->nivelB->id, 'clave' => 'formato_solicitud']))->assertForbidden();
        $this->get(route('tramite.paso2-nivel-documentos.formato-solicitud', ['escuelaNivel' => $this->nivelB->id]))->assertForbidden();
    }

    public function test_un_revisor_sin_asignacion_recibe_403_en_todas_partes(): void
    {
        $this->asignar($this->revisor(), $this->nivelA);
        $ajeno = $this->revisor();
        $escuelaId = $this->nivelA->escuela_id;
        $this->actingAs($ajeno);

        $this->get(route('sedeq.tramite', ['escuela' => $escuelaId]))->assertForbidden();
        $this->get(route('tramite.paso2-documentos.descargar', ['escuela' => $escuelaId, 'clave' => 'ine']))->assertForbidden();
        $this->get(route('tramite.validacion.reporte', ['escuela' => $escuelaId, 'evaluacion' => 1]))->assertForbidden();
        $this->get(route('tramite.resumen', ['escuela' => $escuelaId]))->assertForbidden();
        foreach ([$this->nivelA, $this->nivelB] as $nivel) {
            $this->get(route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $nivel->id, 'clave' => 'formato_solicitud']))->assertForbidden();
            $this->get(route('tramite.paso2-nivel-documentos.formato-solicitud', ['escuelaNivel' => $nivel->id]))->assertForbidden();
        }
    }

    /** Proyecto 1: el reporte de validación (toda la escuela, y también borradores previos al envío) es solo del dueño. */
    public function test_el_reporte_de_validacion_es_solo_del_dueno(): void
    {
        Storage::fake('documentos');
        Storage::disk('documentos')->put('reportes/r.pdf', 'PDF-REPORTE');
        $escuelaId = $this->nivelA->escuela_id;
        $evaluacion = DB::table('evaluaciones_validacion')->insertGetId(['escuela_id' => $escuelaId, 'archivo_path' => 'reportes/r.pdf', 'lista_para_envio' => true, 'resultados' => '[]']);
        $url = route('tramite.validacion.reporte', ['escuela' => $escuelaId, 'evaluacion' => $evaluacion]);

        // Revisor de un solo nivel (A).
        $soloA = $this->revisor();
        $this->asignar($soloA, $this->nivelA);
        $this->actingAs($soloA)->get($url)->assertForbidden();

        // Revisor de todos los niveles de la escuela (A y B).
        $todos = $this->revisor();
        AsignacionRevision::whereKey($this->nivelA->id)->update(['revisor_id' => $todos->id]);
        $this->asignar($todos, $this->nivelB);
        $this->actingAs($todos)->get($url)->assertForbidden();

        $this->actingAs($this->supervisor())->get($url)->assertForbidden();

        $dueno = $this->actingAs($this->nivelA->escuela->solicitante->user)->get($url);
        $dueno->assertOk();
        $this->assertSame('PDF-REPORTE', $dueno->streamedContent());
    }

    public function test_consultar_revalida_el_acceso_sin_depender_de_la_ruta(): void
    {
        $this->expectException(AuthorizationException::class);

        app(ConsultarTramiteSedeq::class)->ejecutar($this->revisor()->id, $this->nivelA->escuela_id);
    }
}
