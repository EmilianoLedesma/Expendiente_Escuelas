<?php

namespace Tests\Feature\Livewire\Sedeq;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Revision\ResultadosCambiados;
use App\Livewire\Sedeq\TramiteLectura;
use App\Models\AsignacionRevision;
use App\Models\DocumentoEscuelaNivel;
use App\Models\EscuelaNivel;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

class TramiteLecturaTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private EscuelaNivel $nivelA;

    private EscuelaNivel $nivelB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        // Se captura mientras está en captura y luego se envía: las Actions de captura rechazan un trámite enviado.
        $this->nivelA = $this->nivelEnRevision('en_captura');
        $escuelaId = $this->nivelA->escuela_id;
        (new RegistrarResponsableLegal)->ejecutar($escuelaId, new DatosResponsableLegal(tipoPersona: 'fisica', domicilioNotificaciones: 'Calle 1', nombre: 'Juana Pérez'));
        (new RegistrarDocumento)->ejecutar($escuelaId, 'ine', UploadedFile::fake()->createWithContent('ine.pdf', 'X'), new DatosDocumento);
        $this->nivelA->update(['folio_expediente' => 'FOLIO-A-123', 'estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
        $this->nivelB = $this->nivelEn($this->nivelA->escuela, 'secundaria', 'en_revision');
    }

    private function revisorDe(EscuelaNivel $nivel): User
    {
        $revisor = $this->revisor();
        AsignacionRevision::create(['escuela_nivel_id' => $nivel->id, 'revisor_id' => $revisor->id, 'asignado_por' => $this->supervisor()->id, 'asignado_at' => now()]);

        return $revisor;
    }

    private function abrir(User $usuario)
    {
        return $this->actingAs($usuario)->get(route('sedeq.tramite', ['escuela' => $this->nivelA->escuela_id]));
    }

    public function test_el_revisor_ve_los_datos_y_solo_su_nivel(): void
    {
        $respuesta = $this->abrir($this->revisorDe($this->nivelA));

        $respuesta->assertOk()
            ->assertSee($this->nivelA->escuela->plantel->calle)
            ->assertSee('Juana Pérez')
            ->assertSee('Primaria')
            ->assertSee('FOLIO-A-123')
            ->assertSee('En revisión')
            ->assertDontSee('Secundaria')
            ->assertSee(route('tramite.paso2-documentos.descargar', ['escuela' => $this->nivelA->escuela_id, 'clave' => 'ine']), false);
    }

    public function test_supervisor_y_revisor_ven_cada_documento_como_vista_previa_con_su_url(): void
    {
        $escuelaId = $this->nivelA->escuela_id;
        DocumentoEscuelaNivel::create(['escuela_nivel_id' => $this->nivelA->id, 'tipo_documento_id' => TipoDocumento::where('clave', 'formato_solicitud')->value('id'), 'archivo_path' => 'escuela_nivel/x/solicitud.pdf']);
        $urls = [
            route('tramite.paso2-documentos.descargar', ['escuela' => $escuelaId, 'clave' => 'ine']),
            route('tramite.paso2-nivel-documentos.descargar', ['escuelaNivel' => $this->nivelA->id, 'clave' => 'formato_solicitud']),
        ];

        foreach ([$this->supervisor(), $this->revisorDe($this->nivelA)] as $usuario) {
            $html = $this->abrir($usuario)->assertOk()->getContent();

            foreach ($urls as $url) {
                $this->assertMatchesRegularExpression('#<a href="'.preg_quote($url, '#').'"[^>]*x-ref="enlace"#', $html, $url);
            }
            $this->assertSame(2, substr_count($html, 'x-data="vistaPdf"'));
            $this->assertSame(2, substr_count($html, '<dialog'));
            $this->assertStringContainsString('Abrir en pantalla completa', $html);
        }
    }

    public function test_las_secciones_sin_datos_lo_dicen_explicitamente(): void
    {
        $vacio = $this->nivelEnRevision();

        $this->actingAs($this->supervisor())->get(route('sedeq.tramite', ['escuela' => $vacio->escuela_id]))
            ->assertOk()
            ->assertSee('Sin datos del responsable legal')
            ->assertSee('Sin documentos del plantel')
            ->assertSee('Sin documentos del nivel');
    }

    public function test_las_secciones_con_datos_no_muestran_el_texto_de_vacio(): void
    {
        $this->abrir($this->supervisor())->assertOk()
            ->assertDontSee('Sin datos del responsable legal')
            ->assertDontSee('Sin documentos del plantel');
    }

    public function test_el_supervisor_ve_el_aviso_de_resultado_cambiado_solo_en_el_nivel_afectado(): void
    {
        $afectado = $this->nivelA->id;
        $this->mock(ResultadosCambiados::class)->shouldReceive('ejecutar')
            ->andReturnUsing(fn (int $actor, int $nivel) => $nivel === $afectado ? ['documentos' => ['RFC: antes cumplía y ahora «No cumple».']] : []);

        $html = $this->abrir($this->supervisor())->assertOk()
            ->assertSee('Resultado cambiado desde que se aceptó')
            ->assertSee('Documentos generales: RFC: antes cumplía y ahora «No cumple».')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-resultado-cambiado='), 'Solo el nivel afectado lleva el aviso.');
    }

    public function test_sin_resultados_cambiados_la_vista_del_supervisor_no_muestra_aviso(): void
    {
        $this->abrir($this->supervisor())->assertOk()->assertDontSee('Resultado cambiado desde que se aceptó');
    }

    public function test_el_supervisor_ve_todos_los_niveles_que_no_estan_en_captura(): void
    {
        $this->nivelEn($this->nivelA->escuela, 'preescolar', 'en_captura');

        $this->abrir($this->supervisor())
            ->assertOk()
            ->assertSee('Primaria')
            ->assertSee('Secundaria')
            ->assertDontSee('Preescolar');
    }

    public function test_es_de_solo_lectura_sin_formularios_ni_enlaces_de_edicion(): void
    {
        $escuelaId = $this->nivelA->escuela_id;
        $html = $this->abrir($this->supervisor())->assertOk()->getContent();

        $this->assertStringNotContainsString('wire:submit', $html);
        $this->assertStringNotContainsString('aria-label="Tu trámite"', $html, 'No debe mostrarse el recorrido del solicitante.');
        foreach ([
            route('tramite.plantel', ['escuela' => $escuelaId]),
            route('tramite.paso2', ['escuela' => $escuelaId]),
            route('tramite.paso2-documentos', ['escuela' => $escuelaId]),
            route('tramite.validacion', ['escuela' => $escuelaId]),
            route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $this->nivelA->id]),
            route('tramite.paso3-inmueble', ['escuelaNivel' => $this->nivelA->id]),
        ] as $edicion) {
            // Exacto (o con query, p. ej. ?seccion=): las descargas comparten prefijo con las páginas de captura.
            $this->assertDoesNotMatchRegularExpression('#href="'.preg_quote($edicion, '#').'(\?[^"]*)?"#', $html, $edicion);
        }
    }

    public function test_la_vista_de_lectura_no_lleva_sidebar_para_supervisor_ni_revisor(): void
    {
        $this->abrir($this->supervisor())->assertOk()->assertDontSee('aria-label="Mi cuenta"', false);
        $this->abrir($this->revisorDe($this->nivelA))->assertOk()->assertDontSee('aria-label="Mi cuenta"', false);
    }

    public function test_el_solicitante_dueno_no_entra_a_la_vista_sedeq(): void
    {
        $this->abrir($this->nivelA->escuela->solicitante->user)->assertForbidden();
    }

    public function test_muestra_el_resumen_de_una_persona_moral(): void
    {
        $nivel = $this->nivelEnRevision('en_captura');
        (new RegistrarResponsableLegal)->ejecutar($nivel->escuela_id, new DatosResponsableLegal(tipoPersona: 'moral', domicilioNotificaciones: 'Av. 5', razonSocial: 'Colegio Ejemplo SA', nombreRepresentanteLegal: 'Juan Pérez Moral'));
        $nivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);

        $this->actingAs($this->supervisor())->get(route('sedeq.tramite', ['escuela' => $nivel->escuela_id]))
            ->assertOk()
            ->assertSee('Persona moral')
            ->assertSee('Colegio Ejemplo SA')
            ->assertSee('Juan Pérez Moral');
    }

    public function test_el_id_de_escuela_no_se_puede_alterar_desde_el_cliente(): void
    {
        $otra = $this->nivelEnRevision();
        $this->actingAs($this->supervisor());

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(TramiteLectura::class, ['escuela' => $this->nivelA->escuela])->set('escuelaId', $otra->escuela_id);
    }
}
