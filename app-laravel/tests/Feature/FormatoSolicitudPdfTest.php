<?php

namespace Tests\Feature;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Http\Controllers\Tramite\FormatoSolicitudPdfController;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FormatoSolicitudPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_descarga_el_pdf_del_formato_de_solicitud(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: 'fisica',
            domicilioNotificaciones: 'Calle Falsa 123',
            nombre: 'Juana Pérez',
        ));
        $this->actingAs($solicitante->user);

        $response = $this->get(route('tramite.paso2-documentos.formato-solicitud', ['escuela' => $escuela->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $otro = Solicitante::factory()->create();
        $this->actingAs($otro->user);

        $response = $this->get(route('tramite.paso2-documentos.formato-solicitud', ['escuela' => $escuela->id]));

        $response->assertForbidden();
    }

    public function test_la_ruta_la_atiende_un_controlador_no_un_closure(): void
    {
        $this->assertSame(
            FormatoSolicitudPdfController::class,
            Route::getRoutes()->getByName('tramite.paso2-documentos.formato-solicitud')->getActionName(),
        );
    }
}
