<?php

namespace Tests\Feature;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Http\Controllers\Tramite\FormatoSolicitudPdfController;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** WS-5b: el Formato de Solicitud se genera por nivel (D1b) e imprime nivel, turno y tipo de alumnado. */
class FormatoSolicitudPdfTest extends TestCase
{
    use RefreshDatabase;

    private function escuelaNivel(Solicitante $solicitante, ?string $turno = 'matutino', ?string $tipoAlumnado = 'mixto'): EscuelaNivel
    {
        (new CatalogoMinimoSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica',
            domicilioNotificaciones: 'Calle Falsa 123',
            nombre: 'Juana Pérez',
        ));

        return EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
            'turno' => $turno,
            'tipo_alumnado' => $tipoAlumnado,
        ]);
    }

    public function test_descarga_el_pdf_del_formato_de_solicitud_del_nivel(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->escuelaNivel($solicitante);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.paso2-nivel-documentos.formato-solicitud', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /** Decisión 5 del spec: sin turno y tipo de alumnado no hay Formato que firmar. */
    public function test_sin_turno_ni_tipo_de_alumnado_no_se_genera(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->escuelaNivel($solicitante, null, null);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.paso2-nivel-documentos.formato-solicitud', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertNotFound();
    }

    /** @return array<string, array{?string, ?string}> */
    public static function datosParciales(): array
    {
        return ['solo turno' => ['matutino', null], 'solo tipo de alumnado' => [null, 'mixto']];
    }

    #[DataProvider('datosParciales')]
    public function test_con_solo_uno_de_los_dos_datos_no_se_genera(?string $turno, ?string $tipoAlumnado): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuelaNivel = $this->escuelaNivel($solicitante, $turno, $tipoAlumnado);

        $this->actingAs($solicitante->user)
            ->get(route('tramite.paso2-nivel-documentos.formato-solicitud', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertNotFound();
    }

    public function test_un_id_de_nivel_no_numerico_da_404(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)
            ->get('/tramite/paso2/nivel/abc/documentos/formato-solicitud.pdf')
            ->assertNotFound();
    }

    /** Review Focus 2. */
    public function test_un_no_dueno_recibe_403(): void
    {
        $escuelaNivel = $this->escuelaNivel(Solicitante::factory()->create());

        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.paso2-nivel-documentos.formato-solicitud', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertForbidden();
    }

    public function test_la_ruta_la_atiende_un_controlador_no_un_closure(): void
    {
        $this->assertSame(
            FormatoSolicitudPdfController::class,
            Route::getRoutes()->getByName('tramite.paso2-nivel-documentos.formato-solicitud')->getActionName(),
        );
    }

    public function test_la_ruta_por_escuela_ya_no_existe(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('tramite.paso2-documentos.formato-solicitud'));
    }

    public function test_la_vista_imprime_nivel_turno_y_tipo_de_alumnado(): void
    {
        $escuelaNivel = $this->escuelaNivel(Solicitante::factory()->create(), 'vespertino', 'femenino');
        $escuelaNivel->load(['nivelEducativo', 'escuela.responsableLegal.personaFisica', 'escuela.responsableLegal.personaMoral', 'escuela.ternasNombres']);

        $html = view('pdf.formato-solicitud', ['escuela' => $escuelaNivel->escuela, 'escuelaNivel' => $escuelaNivel])->render();

        $this->assertStringContainsString('autorización para impartir educación <u>Primaria</u>', $html);
        $this->assertStringContainsString('en el horario <u>Vespertino</u>', $html);
        $this->assertStringContainsString('con alumnado <u>Femenino</u>', $html);
        $this->assertStringContainsString('Juana Pérez', $html);
    }

    /** WS-5c: estructura oficial del "FORMATO DE SOLICITUD EDUCACIÓN BÁSICA" (docx de SEDEQ). */
    public function test_la_vista_sigue_la_estructura_oficial_con_los_datos_de_persona_fisica(): void
    {
        $escuelaNivel = $this->escuelaNivel(Solicitante::factory()->create());
        $escuelaNivel->escuela->responsableLegal->update(['persona_autorizada_recoger' => 'Luis Mora']);
        $escuelaNivel->escuela->responsableLegal->personaFisica->update([
            'fecha_nacimiento' => '1980-01-31', 'rfc' => 'PEJU800131AB1', 'curp' => 'PEJU800131MQTRRN09',
        ]);
        $escuelaNivel->load(['nivelEducativo', 'escuela.responsableLegal.personaFisica', 'escuela.responsableLegal.personaMoral']);

        $html = view('pdf.formato-solicitud', ['escuela' => $escuelaNivel->escuela, 'escuelaNivel' => $escuelaNivel])->render();

        $this->assertStringContainsString('FORMATO DE SOLICITUD', $html);
        $this->assertStringContainsString('DIRECTOR DE EDUCACIÓN', $html);
        $this->assertStringContainsString('El que suscribe <u>Juana Pérez</u>', $html);
        $this->assertStringContainsString('<u>Calle Falsa 123</u>', $html);
        $this->assertStringContainsString('<u>Luis Mora</u>', $html);
        $this->assertStringContainsString('DEL PROPIETARIO EN CASO DE SER PERSONA FÍSICA', $html);
        $this->assertStringContainsString('31 de enero de 1980', $html);
        $this->assertStringContainsString('PEJU800131AB1', $html);
        $this->assertStringContainsString('PEJU800131MQTRRN09', $html);
        $this->assertStringNotContainsString('DEL PROPIETARIO EN CASO DE SER PERSONA MORAL', $html);
        $this->assertStringContainsString('BAJO PROTESTA DE DECIR VERDAD', $html);
    }

    public function test_la_vista_imprime_los_datos_de_persona_moral_y_su_representante(): void
    {
        $escuelaNivel = $this->escuelaNivel(Solicitante::factory()->create());
        $escuelaNivel->escuela->responsableLegal->delete();
        (new RegistrarResponsableLegal)->ejecutar($escuelaNivel->escuela_id, new DatosResponsableLegal(
            tipoPersona: 'moral',
            domicilioNotificaciones: 'Av. Reforma 10',
            razonSocial: 'Colegio Futuro SA de CV',
            nombreRepresentanteLegal: 'Ana Ruiz',
            numeroEscrituraConstitutiva: '4521',
            fechaEscrituraConstitutiva: '2015-03-09',
            notarioNombre: 'Lic. Pedro Soto',
            notarioNumero: '12',
            notarioCiudad: 'Querétaro',
            folioRegistroPublico: 'RPPC 778',
            fechaInscripcionRpp: '2015-04-20',
        ));
        $escuelaNivel->load(['nivelEducativo', 'escuela.responsableLegal.personaFisica', 'escuela.responsableLegal.personaMoral']);

        $html = view('pdf.formato-solicitud', ['escuela' => $escuelaNivel->escuela, 'escuelaNivel' => $escuelaNivel])->render();

        $this->assertStringContainsString('El que suscribe <u>Ana Ruiz</u>', $html);
        $this->assertStringContainsString('DEL PROPIETARIO EN CASO DE SER PERSONA MORAL', $html);
        $this->assertStringNotContainsString('DEL PROPIETARIO EN CASO DE SER PERSONA FÍSICA', $html);
        foreach (['Colegio Futuro SA de CV', '4521', '9 de marzo de 2015', 'Lic. Pedro Soto', '<u>12</u>', 'RPPC 778', '20 de abril de 2015'] as $dato) {
            $this->assertStringContainsString($dato, $html);
        }
    }
}
