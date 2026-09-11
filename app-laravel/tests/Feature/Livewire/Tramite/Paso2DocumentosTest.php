<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Tramite\Paso2Documentos;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Paso2DocumentosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaConResponsable(string $tipoPersona = 'fisica'): Escuela
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: $tipoPersona,
            nombre: 'Juana Pérez',
        ));

        return $escuela;
    }

    public function test_arranca_en_la_primera_clave_pendiente(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSet('fase', 'ine');
    }

    public function test_sube_ine_y_avanza_a_la_siguiente_clave(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivo', UploadedFile::fake()->create('ine.pdf', 50, 'application/pdf'))
            ->call('guardarDocumentoSimple')
            ->assertSet('fase', 'acta_nacimiento')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documentos_escuela', ['escuela_id' => $escuela->id]);
    }

    public function test_rechaza_archivo_que_no_es_pdf(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivo', UploadedFile::fake()->create('ine.jpg', 50, 'image/jpeg'))
            ->call('guardarDocumentoSimple')
            ->assertHasErrors('archivo');
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $otro = Solicitante::factory()->create();
        $this->actingAs($otro->user);

        $response = $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));

        $response->assertForbidden();
    }

    public function test_sube_escritura_dictamen_y_constancia_en_secuencia(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivo', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');
        $component->set('archivo', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');

        $component->set('archivo', UploadedFile::fake()->create('escritura.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'escritura_publica')
            ->set('acreditacionForm.numeroEscritura', 'E-500')
            ->set('acreditacionForm.notarioNombre', 'Lic. Ana Notaria')
            ->call('guardarAcreditacion')
            ->assertSet('fase', 'dictamen_uso_suelo')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', ['numero_escritura' => 'E-500']);

        $component->set('archivo', UploadedFile::fake()->create('dictamen.pdf', 10, 'application/pdf'))
            ->set('dictamenForm.fechaEmision', now()->toDateString())
            ->call('guardarDictamen')
            ->assertSet('fase', 'constancia_seguridad_estructural')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('documentos_plantel', ['fecha_emision' => now()->toDateString()]);

        $component->set('archivo', UploadedFile::fake()->create('constancia.pdf', 10, 'application/pdf'))
            ->set('constanciaForm.fechaEmision', now()->toDateString())
            ->set('constanciaForm.peritoNombre', 'Ing. Juan Pérez')
            ->set('constanciaForm.peritoRegistroDro', 'DRO-100')
            ->call('guardarConstancia')
            ->assertSet('fase', 'formato_solicitud')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('constancias_seguridad_estructural', ['perito_nombre' => 'Ing. Juan Pérez']);
    }

    public function test_sube_formato_de_solicitud_y_redirige_a_paso2(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSet('fase', 'formato_solicitud')
            ->set('archivo', UploadedFile::fake()->create('firmado.pdf', 10, 'application/pdf'))
            ->call('guardarFormatoSolicitud')
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));

        $this->assertDatabaseHas('documentos_escuela', ['escuela_id' => $escuela->id]);
    }

    public function test_reentrar_con_todo_completo_y_dictamen_vencido_muestra_el_error_sin_reventar(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $registrar->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));

        $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]))->assertOk();

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSet('fase', 'dictamen_uso_suelo')
            ->assertHasErrors('vigencia');
    }

    public function test_reentrar_con_todo_completo_y_vigente_redirige_a_paso2(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));
    }

    public function test_una_fase_manipulada_no_puede_saltarse_la_captura_estructurada(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('fase', 'dictamen_uso_suelo')
            ->set('archivo', UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple')
            ->assertStatus(403);

        $this->assertDatabaseCount('documentos_plantel', 0);
    }

    public function test_bloquea_el_avance_final_si_dictamen_esta_vencido(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'constancia_seguridad_estructural'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $registrar->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSet('fase', 'formato_solicitud')
            ->set('archivo', UploadedFile::fake()->create('firmado.pdf', 10, 'application/pdf'))
            ->call('guardarFormatoSolicitud')
            ->assertHasErrors('vigencia')
            ->assertNoRedirect();
    }

    public function test_escritura_publica_sin_notario_falla_validacion(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivo', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');
        $component->set('archivo', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');

        $component->set('archivo', UploadedFile::fake()->create('escritura.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'escritura_publica')
            ->call('guardarAcreditacion')
            ->assertHasErrors(['acreditacionForm.numeroEscritura', 'acreditacionForm.notarioNombre']);

        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 0);
    }

    public function test_variante_arrendamiento_captura_arrendador_contrato_y_observaciones(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivo', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');
        $component->set('archivo', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');

        $component->set('archivo', UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->set('acreditacionForm.arrendadorComodante', 'Juan Arrendador')
            ->set('acreditacionForm.arrendatarioComodatario', 'Juana Pérez')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.vigenciaContrato', '2030-01-01')
            ->set('acreditacionForm.observaciones', 'Contrato renovable anualmente')
            ->call('guardarAcreditacion')
            ->assertSet('fase', 'dictamen_uso_suelo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'arrendamiento',
            'arrendador_comodante' => 'Juan Arrendador',
            'arrendatario_comodatario' => 'Juana Pérez',
            'observaciones' => 'Contrato renovable anualmente',
        ]);
    }

    public function test_variante_arrendamiento_sin_datos_de_contrato_falla_validacion(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivo', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');
        $component->set('archivo', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');

        $component->set('archivo', UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->call('guardarAcreditacion')
            ->assertHasErrors([
                'acreditacionForm.arrendadorComodante',
                'acreditacionForm.arrendatarioComodatario',
                'acreditacionForm.fechaContrato',
                'acreditacionForm.vigenciaContrato',
            ]);

        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 0);
    }

    public function test_variante_comodato_captura_arrendador_y_contrato(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivo', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');
        $component->set('archivo', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');

        $component->set('archivo', UploadedFile::fake()->create('comodato.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'comodato')
            ->set('acreditacionForm.arrendadorComodante', 'Comodante SA')
            ->set('acreditacionForm.arrendatarioComodatario', 'Juana Pérez')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.vigenciaContrato', '2030-01-01')
            ->call('guardarAcreditacion')
            ->assertSet('fase', 'dictamen_uso_suelo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'comodato',
            'arrendador_comodante' => 'Comodante SA',
        ]);
    }

    public function test_variante_otro_requiere_especificacion(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivo', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');
        $component->set('archivo', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple');

        $component->set('archivo', UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'otro')
            ->call('guardarAcreditacion')
            ->assertHasErrors('acreditacionForm.otroEspecifique');
        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 0);

        $component->set('acreditacionForm.otroEspecifique', 'Posesión por resolución judicial')
            ->call('guardarAcreditacion')
            ->assertSet('fase', 'dictamen_uso_suelo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'otro',
            'otro_especifique' => 'Posesión por resolución judicial',
        ]);
    }
}
