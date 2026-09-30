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

        $this->assertStringContainsString('Nivel educativo: Primaria', $html);
        $this->assertStringContainsString('Turno: Vespertino', $html);
        $this->assertStringContainsString('Tipo de alumnado: Femenino', $html);
        $this->assertStringContainsString('Juana Pérez', $html);
    }
}
