<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EstadoPaso2;
use App\Livewire\Tramite\Paso2Documentos;
use App\Models\DocumentoEscuela;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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
            nombre: $tipoPersona === 'moral' ? null : 'Juana Pérez',
            razonSocial: $tipoPersona === 'moral' ? 'Colegio Ejemplo S.C.' : null,
            nombreRepresentanteLegal: $tipoPersona === 'moral' ? 'Miguel Torres' : null,
            gestorNombre: $tipoPersona === 'fisica_con_gestor' ? 'Gestor Uno' : null,
        ));

        return $escuela;
    }

    public function test_muestra_cuantos_documentos_estan_completos_de_cuantos_aplican(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        $total = count(app(DocumentosCompletos::class)->clavesAplicables('fisica'));

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee("0 de {$total} documentos completos");

        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee("3 de {$total} documentos completos");
    }

    public function test_una_seccion_ya_subida_se_muestra_de_solo_lectura_con_boton_reemplazar(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'), new DatosDocumento);
        $nombreArchivo = basename(DocumentoEscuela::where('escuela_id', $escuela->id)->value('archivo_path'));

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee($nombreArchivo)
            ->assertSee('Reemplazar')
            ->assertDontSee('wire:model="archivos.ine"', false);
    }

    public function test_reemplazar_permite_volver_a_subir_sin_duplicar_la_fila(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        (new RegistrarDocumento)->ejecutar($escuela->id, 'ine', UploadedFile::fake()->create('primero.pdf', 10, 'application/pdf'), new DatosDocumento);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->call('toggleReemplazar', 'ine')
            ->set('archivos.ine', UploadedFile::fake()->create('segundo.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'ine')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('documentos_escuela', 1);
        $this->assertMatchesRegularExpression(
            '#^escuela/'.$escuela->id.'/ine-[0-9A-Z]{26}\.pdf$#',
            DocumentoEscuela::where('escuela_id', $escuela->id)->value('archivo_path'),
        );
    }

    public function test_sube_ine_de_forma_independiente_sin_pasar_por_las_demas_claves(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 50, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'ine')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documentos_escuela', ['escuela_id' => $escuela->id]);
    }

    public function test_guardar_documento_simple_rechaza_una_clave_fuera_de_la_whitelist(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.algo_inventado', UploadedFile::fake()->create('fake.pdf', 50, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'algo_inventado')
            ->assertStatus(403);

        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    public function test_guardar_documento_simple_rechaza_clave_no_aplicable_al_tipo_persona(): void
    {
        $escuela = $this->crearEscuelaConResponsable('fisica');
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.escritura_poder_facultades', UploadedFile::fake()->create('poder.pdf', 50, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'escritura_poder_facultades')
            ->assertStatus(403);

        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    public function test_moral_sube_acta_constitutiva_como_documento_de_escuela(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable('moral');
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee('Acta constitutiva')
            ->set('archivos.acta_constitutiva', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'acta_constitutiva')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('documentos_escuela', 1);
    }

    public function test_fisica_con_gestor_sube_poder_gestor(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable('fisica_con_gestor');
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee('Poder general para actos de administración (gestor)')
            ->set('archivos.poder_gestor', UploadedFile::fake()->create('poder.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'poder_gestor')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('documentos_escuela', 1);
    }

    public function test_sube_documento_de_plantel_nuevo_y_queda_en_documentos_plantel(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee('Visto Bueno / Dictamen de Protección Civil')
            ->set('archivos.visto_bueno_proteccion_civil', UploadedFile::fake()->create('vb.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'visto_bueno_proteccion_civil')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('documentos_plantel', 1);
        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    public function test_poder_gestor_y_acta_constitutiva_no_aplican_a_otros_tipos_de_persona(): void
    {
        $moral = $this->crearEscuelaConResponsable('moral');
        $this->actingAs($moral->solicitante->user);
        Livewire::test(Paso2Documentos::class, ['escuela' => $moral])
            ->set('archivos.poder_gestor', UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'poder_gestor')
            ->assertStatus(403);

        $fisica = $this->crearEscuelaConResponsable('fisica');
        $this->actingAs($fisica->solicitante->user);
        Livewire::test(Paso2Documentos::class, ['escuela' => $fisica])
            ->assertDontSee('Acta constitutiva')
            ->set('archivos.acta_constitutiva', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'acta_constitutiva')
            ->assertStatus(403);
    }

    public function test_rechaza_archivo_que_no_es_pdf(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', UploadedFile::fake()->create('ine.jpg', 50, 'image/jpeg'))
            ->call('guardarDocumentoSimple', 'ine')
            ->assertHasErrors('archivos.ine');
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $otro = Solicitante::factory()->create();
        $this->actingAs($otro->user);

        $response = $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));

        $response->assertForbidden();
    }

    public function test_sube_acreditacion_de_forma_independiente(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'ine');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('escritura.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'escritura_publica')
            ->set('acreditacionForm.numeroEscritura', 'E-500')
            ->set('acreditacionForm.notarioNombre', 'Lic. Ana Notaria')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', ['numero_escritura' => 'E-500']);
    }

    public function test_sube_dictamen_de_forma_independiente(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.dictamen_uso_suelo', UploadedFile::fake()->create('dictamen.pdf', 10, 'application/pdf'))
            ->set('dictamenForm.fechaEmision', now()->toDateString())
            ->call('guardarDictamen')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('documentos_plantel', ['fecha_emision' => now()->toDateString()]);
    }

    public function test_sube_constancia_de_forma_independiente(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.constancia_seguridad_estructural', UploadedFile::fake()->create('constancia.pdf', 10, 'application/pdf'))
            ->set('constanciaForm.fechaEmision', now()->toDateString())
            ->set('constanciaForm.peritoNombre', 'Ing. Juan Pérez')
            ->set('constanciaForm.peritoRegistroDro', 'DRO-100')
            ->call('guardarConstancia')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('constancias_seguridad_estructural', ['perito_nombre' => 'Ing. Juan Pérez']);
    }

    public function test_sube_dictamen_antes_que_acreditacion_demuestra_orden_independiente(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);

        // Upload dictamen BEFORE acreditacion to prove order independence
        $component->set('archivos.dictamen_uso_suelo', UploadedFile::fake()->create('dictamen.pdf', 10, 'application/pdf'))
            ->set('dictamenForm.fechaEmision', now()->toDateString())
            ->call('guardarDictamen')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('documentos_plantel', ['fecha_emision' => now()->toDateString()]);

        // Then upload acreditacion
        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('escritura.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'escritura_publica')
            ->set('acreditacionForm.numeroEscritura', 'E-500')
            ->set('acreditacionForm.notarioNombre', 'Lic. Ana Notaria')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', ['numero_escritura' => 'E-500']);
    }

    public function test_subir_el_ultimo_documento_simple_redirige_a_paso2(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (array_diff(app(DocumentosCompletos::class)->clavesAplicables('fisica'), ['certificado_numero_oficial']) as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.certificado_numero_oficial', UploadedFile::fake()->create('cno.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'certificado_numero_oficial')
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));
    }

    public function test_el_formato_de_solicitud_ya_no_aparece_en_documentos(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertDontSeeHtml('data-clave="formato_solicitud"')
            ->assertDontSee('Generar y descargar Formato de Solicitud');
    }

    /** @return array<string, array{string}> */
    public static function tiposPersona(): array
    {
        return ['fisica' => ['fisica'], 'moral' => ['moral'], 'fisica_con_gestor' => ['fisica_con_gestor']];
    }

    /** WS-5a M1: toda clave aplicable debe tener una fila con control de carga. */
    #[DataProvider('tiposPersona')]
    public function test_toda_clave_aplicable_renderiza_su_fila(string $tipoPersona): void
    {
        $escuela = $this->crearEscuelaConResponsable($tipoPersona);
        $this->actingAs($escuela->solicitante->user);

        $componente = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);

        foreach (app(DocumentosCompletos::class)->clavesAplicables($tipoPersona) as $clave) {
            $componente->assertSeeHtml('data-clave="'.$clave.'"');
        }
    }

    /** M1: una fila nueva del catálogo sin bloque propio se ve y se puede completar. */
    public function test_una_clave_nueva_del_catalogo_sin_bloque_propio_se_puede_subir(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        DB::table('tipos_documentos')->insert([
            'clave' => 'documento_de_prueba_m1', 'nombre' => 'Documento de prueba M1',
            'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('data-clave="documento_de_prueba_m1"')
            ->assertSee('Documento de prueba M1') // M6: título del catálogo, no la clave cruda
            ->set('archivos.documento_de_prueba_m1', UploadedFile::fake()->create('m1.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'documento_de_prueba_m1')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documentos_plantel', ['plantel_id' => $escuela->plantel_id]);
    }

    /** O2: la vía genérica no acepta claves con datos estructurados (saltaría la vigencia del dictamen). */
    public function test_guardar_documento_simple_rechaza_una_clave_con_datos_estructurados(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'dictamen_uso_suelo')
            ->assertStatus(403);

        $this->assertDatabaseCount('documentos_plantel', 0);
    }

    public function test_reentrar_con_todo_completo_y_dictamen_vencido_muestra_el_error_sin_reventar(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (array_diff(app(DocumentosCompletos::class)->clavesAplicables('fisica'), ['dictamen_uso_suelo']) as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $registrar->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));

        $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]))->assertOk();

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertHasErrors('vigencia');
    }

    public function test_reentrar_con_todo_completo_y_vigente_redirige_a_paso2(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (app(DocumentosCompletos::class)->clavesAplicables('fisica') as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));
    }

    public function test_bloquea_el_avance_final_si_dictamen_esta_vencido(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (array_diff(app(DocumentosCompletos::class)->clavesAplicables('fisica'), ['dictamen_uso_suelo', 'certificado_numero_oficial']) as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $registrar->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), new DatosDocumento(
            fechaEmision: now()->subDays(60)->toDateString(),
        ));

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.certificado_numero_oficial', UploadedFile::fake()->create('cno.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'certificado_numero_oficial')
            ->assertHasErrors('vigencia')
            ->assertNoRedirect();
    }

    public function test_escritura_publica_sin_notario_falla_validacion(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'ine');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('escritura.pdf', 10, 'application/pdf'))
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
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'ine');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->set('acreditacionForm.arrendadorComodante', 'Juan Arrendador')
            ->set('acreditacionForm.arrendatarioComodatario', 'Juana Pérez')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.vigenciaContrato', '2030-01-01')
            ->set('acreditacionForm.observaciones', 'Contrato renovable anualmente')
            ->call('guardarAcreditacion')
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
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'ine');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'))
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
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'ine');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('comodato.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'comodato')
            ->set('acreditacionForm.arrendadorComodante', 'Comodante SA')
            ->set('acreditacionForm.arrendatarioComodatario', 'Juana Pérez')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.vigenciaContrato', '2030-01-01')
            ->call('guardarAcreditacion')
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
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'ine');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'otro')
            ->call('guardarAcreditacion')
            ->assertHasErrors('acreditacionForm.otroEspecifique');
        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 0);

        $component->set('acreditacionForm.otroEspecifique', 'Posesión por resolución judicial')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'otro',
            'otro_especifique' => 'Posesión por resolución judicial',
        ]);
    }

    // WS-2.4a — un DatosInvalidos lanzado por el caso de uso (p. ej. un caller
    // que se salte la validación de Livewire) debe convertirse en un error de
    // campo, no en un 500.
    public function test_datos_invalidos_del_caso_de_uso_se_convierte_en_error_de_campo(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        $this->mock(RegistrarDocumento::class, function ($mock) {
            $mock->shouldReceive('ejecutar')->andThrow(new DatosInvalidos(['archivos.ine' => 'El archivo debe ser un PDF.']));
        });

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'ine')
            ->assertHasErrors('archivos.ine');

        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    // WS-2.4b — un PrecondicionIncumplida lanzado por el caso de uso (p. ej. un
    // caller que llegue a este punto sin responsable legal) debe redirigir a
    // Paso 2, no producir un 500.
    public function test_precondicion_incumplida_del_caso_de_uso_redirige_a_paso_2(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        $this->mock(RegistrarDocumento::class, function ($mock) {
            $mock->shouldReceive('ejecutar')->andThrow(new PrecondicionIncumplida(
                EstadoPaso2::RESPONSABLE,
                'Captura el responsable legal (Paso 2) antes de subir documentos.',
            ));
        });

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'ine')
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));

        $this->assertDatabaseCount('documentos_escuela', 0);
    }
}
