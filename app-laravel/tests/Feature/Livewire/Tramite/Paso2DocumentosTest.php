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
use App\Models\DocumentoPlantel;
use App\Models\EntidadFederativa;
use App\Models\Escuela;
use App\Models\Municipio;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\DictamenDeEjemplo;
use Tests\TestCase;

class Paso2DocumentosTest extends TestCase
{
    use CompletaPaso2;
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
        $this->elegirNivel($escuela->id);

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

    public function test_cada_documento_aplicable_tiene_su_propio_paso(): void
    {
        $escuela = $this->crearEscuelaConResponsable('moral');
        $this->actingAs($escuela->solicitante->user);

        $total = count(app(DocumentosCompletos::class)->clavesAplicables('moral'));
        $html = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])->html();

        $this->assertStringContainsString('data-stepper-nav', $html);
        $this->assertSame($total, preg_match_all('/<div role="group"[^>]*data-paso="\d+"/', $html));
        $this->assertStringContainsString('data-paso="'.($total - 1).'"', $html);
        $this->assertStringNotContainsString('data-paso="'.$total.'"', $html);
    }

    public function test_un_paso_con_su_documento_subido_se_marca_completo(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        $inmueble = ['escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'visto_bueno_proteccion_civil', 'plano_inmueble', 'certificado_numero_oficial'];
        $aplicables = app(DocumentosCompletos::class)->clavesAplicables('fisica');

        $this->assertStringNotContainsString('(paso completo)', Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])->html());

        foreach (array_intersect($inmueble, $aplicables) as $clave) {
            $tipo = DB::table('tipos_documentos')->where('clave', $clave)->first();
            $fila = ['tipo_documento_id' => $tipo->id, 'ruta_archivo' => "x/{$clave}.pdf", 'nombre_original' => "{$clave}.pdf"];
            // Los documentos de ámbito plantel se guardan en documentos_plantel, los demás en documentos_escuela.
            $tipo->ambito === 'plantel'
                ? DocumentoPlantel::create($fila + ['plantel_id' => $escuela->plantel_id])
                : DocumentoEscuela::create($fila + ['escuela_id' => $escuela->id]);
        }

        $subidos = count(array_intersect($inmueble, $aplicables));
        $this->assertGreaterThan(0, $subidos);
        $this->assertSame($subidos, substr_count(Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])->html(), '(paso completo)'));
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
            ->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne')
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
            ->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne')
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

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee('Visto Bueno / Dictamen de Protección Civil');
        $this->llenarProteccionCivil($component)
            ->call('guardarProteccionCivil')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('documentos_plantel', 1);
        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    /**
     * Llena el Visto Bueno de Protección Civil con una captura válida del Municipio de Querétaro;
     * $campos pisa lo que cada prueba necesite. @param  array<string, string>  $campos
     */
    private function llenarProteccionCivil(Testable $componente, array $campos = []): Testable
    {
        $valores = [
            'tipoInstrumento' => 'visto_bueno',
            'fechaEmision' => now()->subDays(10)->toDateString(),
            'autoridadEmisora' => 'Coordinación Municipal de Protección Civil',
            'jurisdiccionEmisora' => 'municipio_queretaro',
            'ejercicio' => (string) now()->year,
            'clasificacionRiesgo' => 'alto',
            'tipoVigencia' => 'sin_fecha_expresa',
            ...$campos,
        ];
        $componente->set('archivos.visto_bueno_proteccion_civil', UploadedFile::fake()->create('vb.pdf', 10, 'application/pdf'));
        foreach ($valores as $campo => $valor) {
            $componente->set("proteccionCivilForm.{$campo}", $valor);
        }

        return $componente;
    }

    private function componenteProteccionCivil(): Testable
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        return Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
    }

    public function test_proteccion_civil_muestra_los_grupos_y_los_campos_condicionales(): void
    {
        $componente = $this->componenteProteccionCivil();

        foreach (['tipoInstrumento', 'fechaEmision', 'folioDocumento', 'autoridadEmisora', 'jurisdiccionEmisora', 'clasificacionRiesgo', 'tipoVigencia', 'condicionantes'] as $campo) {
            $componente->assertSeeHtml("id=\"proteccionCivilForm.{$campo}\"");
        }
        $componente->assertDontSeeHtml('id="proteccionCivilForm.tipoInstrumentoOtro"')
            ->assertDontSeeHtml('id="proteccionCivilForm.fechaVencimientoDocumento"')
            ->assertDontSeeHtml('id="proteccionCivilForm.ejercicio"');

        $componente->set('proteccionCivilForm.tipoInstrumento', 'otro')->assertSeeHtml('id="proteccionCivilForm.tipoInstrumentoOtro"');
        $componente->set('proteccionCivilForm.tipoInstrumento', 'visto_bueno')->set('proteccionCivilForm.jurisdiccionEmisora', 'municipio_queretaro')
            ->assertSeeHtml('id="proteccionCivilForm.ejercicio"');
        $componente->set('proteccionCivilForm.jurisdiccionEmisora', 'otra')->assertDontSeeHtml('id="proteccionCivilForm.ejercicio"');
        $componente->set('proteccionCivilForm.tipoVigencia', 'fecha_expresa')->assertSeeHtml('id="proteccionCivilForm.fechaVencimientoDocumento"');
    }

    public function test_proteccion_civil_sin_los_obligatorios_marca_cada_campo(): void
    {
        $componente = $this->componenteProteccionCivil();

        $componente->set('archivos.visto_bueno_proteccion_civil', UploadedFile::fake()->create('vb.pdf', 10, 'application/pdf'))
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['proteccionCivilForm.tipoInstrumento', 'proteccionCivilForm.fechaEmision', 'proteccionCivilForm.autoridadEmisora', 'proteccionCivilForm.jurisdiccionEmisora', 'proteccionCivilForm.clasificacionRiesgo', 'proteccionCivilForm.tipoVigencia']);
        $this->assertDatabaseCount('documentos_plantel', 0);
    }

    public function test_proteccion_civil_guarda_la_captura_sin_calcular_30_dias_y_con_el_vencimiento_normativo(): void
    {
        $componente = $this->componenteProteccionCivil();
        $fecha = now()->subDays(40)->toDateString();

        $this->llenarProteccionCivil($componente, ['fechaEmision' => $fecha, 'folioDocumento' => '  VB-001 ', 'condicionantes' => 'Aforo máximo.'])
            ->call('guardarProteccionCivil')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documentos_plantel', ['fecha_emision' => $fecha, 'fecha_vigencia' => null]);
        $this->assertDatabaseHas('vistos_buenos_proteccion_civil', [
            'tipo_instrumento' => 'visto_bueno', 'jurisdiccion_emisora' => 'municipio_queretaro', 'folio_documento' => 'VB-001',
            'clasificacion_riesgo' => 'alto', 'ejercicio' => now()->year, 'regimen_normativo' => 'reglamento_pc_queretaro_2017',
            'fecha_vencimiento_normativo' => now()->year.'-12-31', 'tipo_vigencia' => 'sin_fecha_expresa', 'condicionantes' => 'Aforo máximo.',
        ]);
    }

    public function test_proteccion_civil_de_otra_autoridad_no_guarda_ejercicio_ni_vencimiento_normativo(): void
    {
        $componente = $this->componenteProteccionCivil();

        $this->llenarProteccionCivil($componente, ['jurisdiccionEmisora' => 'otra'])
            ->call('guardarProteccionCivil')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vistos_buenos_proteccion_civil', ['jurisdiccion_emisora' => 'otra', 'ejercicio' => null, 'regimen_normativo' => null, 'fecha_vencimiento_normativo' => null]);
    }

    public function test_proteccion_civil_con_fecha_expresa_la_pide_y_la_guarda_y_sin_ella_no_inventa_ninguna(): void
    {
        $componente = $this->componenteProteccionCivil();
        $vence = now()->addMonths(3)->toDateString();

        $this->llenarProteccionCivil($componente, ['tipoVigencia' => 'fecha_expresa'])
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['proteccionCivilForm.fechaVencimientoDocumento']);

        $componente->set('proteccionCivilForm.fechaVencimientoDocumento', $vence)->call('guardarProteccionCivil')->assertHasNoErrors();
        $this->assertDatabaseHas('vistos_buenos_proteccion_civil', ['tipo_vigencia' => 'fecha_expresa', 'fecha_vencimiento_documento' => $vence]);

        $componente->set('proteccionCivilForm.tipoVigencia', 'sin_fecha_expresa')
            ->set('archivos.visto_bueno_proteccion_civil', UploadedFile::fake()->create('vb2.pdf', 10, 'application/pdf'))
            ->call('guardarProteccionCivil')->assertHasNoErrors();
        $this->assertDatabaseHas('vistos_buenos_proteccion_civil', ['tipo_vigencia' => 'sin_fecha_expresa', 'fecha_vencimiento_documento' => null]);
    }

    public function test_proteccion_civil_rechaza_fechas_incoherentes(): void
    {
        $componente = $this->componenteProteccionCivil();

        $this->llenarProteccionCivil($componente, ['fechaEmision' => now()->addDay()->toDateString()])
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['proteccionCivilForm.fechaEmision']);

        $componente->set('proteccionCivilForm.fechaEmision', '2026-05-10')
            ->set('proteccionCivilForm.tipoVigencia', 'fecha_expresa')
            ->set('proteccionCivilForm.fechaVencimientoDocumento', '2026-05-09')
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['proteccionCivilForm.fechaVencimientoDocumento']);

        $componente->set('proteccionCivilForm.tipoVigencia', 'sin_fecha_expresa')
            ->set('proteccionCivilForm.ejercicio', '2016')
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['proteccionCivilForm.ejercicio']);
        $this->assertDatabaseCount('documentos_plantel', 0);
    }

    public function test_proteccion_civil_otro_pide_descripcion_y_cambiar_de_otro_no_la_conserva(): void
    {
        $componente = $this->componenteProteccionCivil();

        $this->llenarProteccionCivil($componente, ['tipoInstrumento' => 'otro'])
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['proteccionCivilForm.tipoInstrumentoOtro']);

        $componente->set('proteccionCivilForm.tipoInstrumentoOtro', 'Opinión técnica')->set('proteccionCivilForm.tipoInstrumento', 'dictamen')
            ->call('guardarProteccionCivil')->assertHasNoErrors();
        $this->assertDatabaseHas('vistos_buenos_proteccion_civil', ['tipo_instrumento' => 'dictamen', 'tipo_instrumento_otro' => null]);
    }

    public function test_proteccion_civil_acepta_solo_pdf_de_hasta_10_mb(): void
    {
        $componente = $this->componenteProteccionCivil();

        $this->llenarProteccionCivil($componente)
            ->set('archivos.visto_bueno_proteccion_civil', UploadedFile::fake()->create('vb.pdf', 10241, 'application/pdf'))
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['archivos.visto_bueno_proteccion_civil']);
        $componente->set('archivos.visto_bueno_proteccion_civil', UploadedFile::fake()->create('vb.txt', 10, 'text/plain'))
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['archivos.visto_bueno_proteccion_civil']);
        $this->assertDatabaseCount('documentos_plantel', 0);
    }

    public function test_proteccion_civil_con_riesgo_medio_en_queretaro_guarda_y_muestra_un_aviso_que_no_bloquea(): void
    {
        $componente = $this->componenteProteccionCivil();

        $this->llenarProteccionCivil($componente, ['clasificacionRiesgo' => 'medio'])
            ->call('guardarProteccionCivil')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('vistos_buenos_proteccion_civil', 1);
        $componente->assertSee('art. 48, fr. I, inciso m)');
    }

    public function test_proteccion_civil_reemplazo_conserva_el_pdf_anterior_si_la_captura_es_invalida(): void
    {
        $componente = $this->componenteProteccionCivil();
        $this->llenarProteccionCivil($componente)->call('guardarProteccionCivil')->assertHasNoErrors();
        $ruta = DocumentoPlantel::first()->archivo_path;

        $componente->call('toggleReemplazar', 'visto_bueno_proteccion_civil');
        $this->llenarProteccionCivil($componente, ['autoridadEmisora' => ''])
            ->call('guardarProteccionCivil')
            ->assertHasErrors(['proteccionCivilForm.autoridadEmisora']);

        $this->assertSame($ruta, DocumentoPlantel::first()->archivo_path);
        Storage::disk('documentos')->assertExists($ruta);
    }

    public function test_proteccion_civil_ya_no_se_sube_por_la_via_generica(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.visto_bueno_proteccion_civil', UploadedFile::fake()->create('vb.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'visto_bueno_proteccion_civil')
            ->assertForbidden();
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
            ->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne')
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
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('escritura.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'escritura_publica')
            ->set('acreditacionForm.numeroEscritura', 'E-500')
            ->set('acreditacionForm.fechaEscritura', '2024-03-15')
            ->set('acreditacionForm.propietarioNombre', 'Colegio Norte SA de CV')
            ->set('acreditacionForm.notarioNombre', 'Lic. Ana Notaria')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', ['numero_escritura' => 'E-500', 'fecha_escritura' => '2024-03-15']);
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
            ->set('dictamenForm.autoridadExpide', 'Dirección de Desarrollo Urbano')
            ->set('dictamenForm.folioDictamen', 'DUS-2026-001')
            ->set('dictamenForm.giroAutorizado', 'Escuela de educación básica')
            ->set('dictamenForm.tipoVigencia', 'sin_fecha_expresa')
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
            ->set('constanciaForm.tipoVigencia', 'fecha_expresa')
            ->set('constanciaForm.vigenciaConstancia', now()->addYear()->toDateString())
            ->set('constanciaForm.calidadProfesional', 'corresponsable_seguridad_estructural')
            ->call('guardarConstancia')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('constancias_seguridad_estructural', ['perito_nombre' => 'Ing. Juan Pérez', 'calidad_profesional' => 'corresponsable_seguridad_estructural']);
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
            ->set('dictamenForm.autoridadExpide', 'Dirección de Desarrollo Urbano')
            ->set('dictamenForm.folioDictamen', 'DUS-2026-001')
            ->set('dictamenForm.giroAutorizado', 'Escuela de educación básica')
            ->set('dictamenForm.tipoVigencia', 'sin_fecha_expresa')
            ->call('guardarDictamen')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('documentos_plantel', ['fecha_emision' => now()->toDateString()]);

        // Then upload acreditacion
        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('escritura.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'escritura_publica')
            ->set('acreditacionForm.numeroEscritura', 'E-500')
            ->set('acreditacionForm.fechaEscritura', '2024-03-15')
            ->set('acreditacionForm.propietarioNombre', 'Colegio Norte SA de CV')
            ->set('acreditacionForm.notarioNombre', 'Lic. Ana Notaria')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', ['numero_escritura' => 'E-500', 'fecha_escritura' => '2024-03-15']);
    }

    /** 2026-10-20: los niveles ya se eligieron antes de los documentos, así que completar los documentos lleva al resumen. */
    public function test_subir_el_ultimo_documento_simple_vuelve_al_resumen(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (array_diff(app(DocumentosCompletos::class)->clavesAplicables('fisica'), ['plano_inmueble']) as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.plano_inmueble', UploadedFile::fake()->create('plano.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'plano_inmueble')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]));
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
        $registrar->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), DictamenDeEjemplo::vencido());

        $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]))->assertOk();

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertHasErrors('vigencia');
    }

    /** WS-7b-1: con todo completo y vigente la checklist se abre para revisar o reemplazar (hub, aviso D2). */
    public function test_reentrar_con_todo_completo_y_vigente_muestra_la_checklist_para_revisar(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        $aplicables = app(DocumentosCompletos::class)->clavesAplicables('fisica');
        foreach ($aplicables as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $total = count($aplicables);

        $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee("{$total} de {$total} documentos completos")
            ->assertSee('Reemplazar');
    }

    /** WS-7b-1: con niveles ya elegidos, completar los documentos vuelve al resumen, no al formulario del responsable. */
    public function test_completar_los_documentos_con_niveles_ya_elegidos_vuelve_al_resumen(): void
    {
        Storage::fake('documentos');
        (new CatalogoMinimoSeeder)->run();
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (array_diff(app(DocumentosCompletos::class)->clavesAplicables('fisica'), ['plano_inmueble']) as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.plano_inmueble', UploadedFile::fake()->create('plano.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'plano_inmueble')
            ->assertSessionHas('toast.titulo', 'Documentos completos')
            ->assertSessionHas('toast.descripcion', 'Continúa con los documentos de cada nivel desde el resumen de tu trámite.')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuela->id]));
    }

    public function test_bloquea_el_avance_final_si_dictamen_esta_vencido(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (array_diff(app(DocumentosCompletos::class)->clavesAplicables('fisica'), ['dictamen_uso_suelo', 'plano_inmueble']) as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }
        $registrar->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), DictamenDeEjemplo::vencido());

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.plano_inmueble', UploadedFile::fake()->create('plano.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'plano_inmueble')
            ->assertHasErrors('vigencia')
            ->assertNoRedirect();
    }

    public function test_escritura_publica_sin_notario_falla_validacion(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('escritura.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'escritura_publica')
            ->call('guardarAcreditacion')
            ->assertHasErrors(['acreditacionForm.numeroEscritura', 'acreditacionForm.fechaEscritura', 'acreditacionForm.notarioNombre']);

        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 0);
    }

    public function test_variante_arrendamiento_captura_arrendador_contrato_y_observaciones(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->set('acreditacionForm.arrendadorComodante', 'Juan Arrendador')
            ->set('acreditacionForm.arrendatarioComodatario', 'Juana Pérez')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.fechaInicioVigencia', '2026-02-01')
            ->set('acreditacionForm.usoAutorizado', 'Prestación de servicio educativo')
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

    public function test_constancia_muestra_cedula_y_autoridad_del_perito(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('id="constanciaForm.peritoCedulaProfesional"')
            ->assertSeeHtml('id="constanciaForm.peritoRegistroAutoridad"');
    }

    public function test_constancia_guarda_cedula_y_autoridad_del_perito(): void
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
            ->set('constanciaForm.tipoVigencia', 'fecha_expresa')
            ->set('constanciaForm.vigenciaConstancia', now()->addYear()->toDateString())
            ->set('constanciaForm.calidadProfesional', 'dro')
            ->set('constanciaForm.peritoCedulaProfesional', '1234567')
            ->set('constanciaForm.peritoRegistroAutoridad', 'Colegio de Ingenieros')
            ->call('guardarConstancia')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('constancias_seguridad_estructural', [
            'perito_cedula_profesional' => '1234567',
            'perito_registro_autoridad' => 'Colegio de Ingenieros',
        ]);
    }

    public function test_acreditacion_muestra_ratificado_notario_en_arrendamiento_y_comodato_pero_no_en_escritura_ni_otro(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertDontSeeHtml('id="acreditacionForm.ratificadoNotario"')
            ->set('acreditacionForm.tipo', 'comodato')
            ->assertSeeHtml('id="acreditacionForm.ratificadoNotario"')
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->assertSeeHtml('id="acreditacionForm.ratificadoNotario"')
            ->set('acreditacionForm.tipo', 'otro')
            ->assertDontSeeHtml('id="acreditacionForm.ratificadoNotario"');
    }

    public function test_acreditacion_cada_variante_muestra_solo_sus_campos(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('id="acreditacionForm.propietarioNombre"')
            ->assertSee('Número de notaría')
            ->assertSeeHtml('id="acreditacionForm.notarioEntidadFederativaId"')
            ->assertSeeHtml('id="acreditacionForm.notarioMunicipioId"')
            ->assertDontSeeHtml('id="acreditacionForm.arrendadorComodante"')
            ->assertDontSeeHtml('id="acreditacionForm.otroDerecho"')
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->assertSeeHtml('id="acreditacionForm.arrendadorComodante"')
            ->assertSeeHtml('id="acreditacionForm.fechaInicioVigencia"')
            ->assertSee('Arrendador')
            ->assertDontSeeHtml('id="acreditacionForm.propietarioNombre"')
            ->assertDontSeeHtml('id="acreditacionForm.folioRpp"')
            ->set('acreditacionForm.tipo', 'comodato')
            ->assertSee('Comodante')
            ->assertSee('Comodatario')
            ->set('acreditacionForm.tipo', 'otro')
            ->assertSeeHtml('id="acreditacionForm.otroDerecho"')
            ->assertSeeHtml('id="acreditacionForm.otroReferencia"')
            ->assertDontSeeHtml('id="acreditacionForm.numeroEscritura"')
            ->assertDontSeeHtml('id="acreditacionForm.arrendadorComodante"');
    }

    public function test_acreditacion_oculta_la_fecha_de_fin_con_vigencia_indeterminada(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('acreditacionForm.tipo', 'comodato')
            ->assertSeeHtml('id="acreditacionForm.vigenciaContrato"')
            ->set('acreditacionForm.vigenciaIndeterminada', true)
            ->assertDontSeeHtml('id="acreditacionForm.vigenciaContrato"');
    }

    public function test_acreditacion_muestra_datos_de_ratificacion_solo_si_esta_ratificado(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->assertDontSeeHtml('id="acreditacionForm.ratificacionFecha"')
            ->set('acreditacionForm.ratificadoNotario', true)
            ->assertSeeHtml('id="acreditacionForm.ratificacionFecha"')
            ->assertSeeHtml('id="acreditacionForm.ratificacionNotarioNombre"')
            ->assertSeeHtml('id="acreditacionForm.ratificacionNotariaNumero"');
    }

    public function test_acreditacion_muestra_facultades_solo_si_el_arrendador_no_es_propietario(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->assertSeeHtml('id="acreditacionForm.arrendadorEsPropietario"')
            ->assertDontSeeHtml('id="acreditacionForm.facultadesReferencia"')
            ->set('acreditacionForm.arrendadorEsPropietario', false)
            ->assertSeeHtml('id="acreditacionForm.facultadesReferencia"');
    }

    public function test_comodato_guarda_ratificado_notario(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'comodato')
            ->set('acreditacionForm.arrendadorComodante', 'Juan Comodante')
            ->set('acreditacionForm.arrendatarioComodatario', 'Juana Pérez')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.fechaInicioVigencia', '2026-02-01')
            ->set('acreditacionForm.usoAutorizado', 'Prestación de servicio educativo')
            ->set('acreditacionForm.vigenciaContrato', '2030-01-01')
            ->set('acreditacionForm.ratificadoNotario', true)
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', ['tipo' => 'comodato', 'ratificado_notario' => true]);
    }

    public function test_escritura_publica_muestra_fecha_de_la_escritura_y_arrendamiento_no(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('id="acreditacionForm.fechaEscritura"')
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->assertDontSeeHtml('id="acreditacionForm.fechaEscritura"');
    }

    /** Escuela lista para capturar la constancia: los documentos previos del plantel ya subidos. */
    private function escuelaParaConstancia(): Escuela
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        return $escuela;
    }

    /** Llena la constancia con captura válida; $campos pisa lo que cada prueba necesite. @param  array<string, string>  $campos */
    private function llenarConstancia(Testable $componente, array $campos = []): Testable
    {
        $valores = [
            'fechaEmision' => now()->subDays(10)->toDateString(),
            'peritoNombre' => 'Ing. Juan Pérez',
            'calidadProfesional' => 'corresponsable_seguridad_estructural',
            'peritoRegistroDro' => 'CSE-100',
            'tipoVigencia' => 'sin_fecha_expresa',
            ...$campos,
        ];
        $componente->set('archivos.constancia_seguridad_estructural', UploadedFile::fake()->create('constancia.pdf', 10, 'application/pdf'));
        foreach ($valores as $campo => $valor) {
            $componente->set("constanciaForm.{$campo}", $valor);
        }

        return $componente;
    }

    public function test_constancia_pide_indicar_si_el_documento_trae_fecha_de_vencimiento_y_la_guarda(): void
    {
        $escuela = $this->escuelaParaConstancia();
        $vigencia = now()->addYear()->toDateString();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('id="constanciaForm.tipoVigencia"')
            ->assertDontSeeHtml('id="constanciaForm.vigenciaConstancia"');
        $this->llenarConstancia($component, ['tipoVigencia' => ''])
            ->call('guardarConstancia')
            ->assertHasErrors(['constanciaForm.tipoVigencia']);
        $this->assertDatabaseCount('constancias_seguridad_estructural', 0);

        $component->set('constanciaForm.tipoVigencia', 'fecha_expresa')
            ->assertSeeHtml('id="constanciaForm.vigenciaConstancia"')
            ->call('guardarConstancia')
            ->assertHasErrors(['constanciaForm.vigenciaConstancia']);

        $component->set('constanciaForm.vigenciaConstancia', $vigencia)
            ->call('guardarConstancia')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('constancias_seguridad_estructural', ['tipo_vigencia' => 'fecha_expresa', 'vigencia_constancia' => $vigencia]);
    }

    public function test_constancia_sin_fecha_expresa_se_guarda_sin_inventar_vencimiento(): void
    {
        $escuela = $this->escuelaParaConstancia();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $this->llenarConstancia($component, ['tipoVigencia' => 'fecha_expresa', 'vigenciaConstancia' => now()->addYear()->toDateString()])
            ->set('constanciaForm.tipoVigencia', 'sin_fecha_expresa')
            ->call('guardarConstancia')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('constancias_seguridad_estructural', ['tipo_vigencia' => 'sin_fecha_expresa', 'vigencia_constancia' => null]);
        $this->assertDatabaseHas('documentos_plantel', ['fecha_vigencia' => null]);
    }

    public function test_constancia_rechaza_emision_futura_y_vencimiento_anterior_a_la_emision(): void
    {
        $escuela = $this->escuelaParaConstancia();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $this->llenarConstancia($component, ['fechaEmision' => now()->addDay()->toDateString()])
            ->call('guardarConstancia')
            ->assertHasErrors(['constanciaForm.fechaEmision']);

        $component->set('constanciaForm.fechaEmision', '2026-05-10')
            ->set('constanciaForm.tipoVigencia', 'fecha_expresa')
            ->set('constanciaForm.vigenciaConstancia', '2026-05-09')
            ->call('guardarConstancia')
            ->assertHasErrors(['constanciaForm.vigenciaConstancia']);
        $this->assertDatabaseCount('constancias_seguridad_estructural', 0);
    }

    public function test_constancia_calidad_profesional_es_obligatoria_y_otra_pide_descripcion(): void
    {
        $escuela = $this->escuelaParaConstancia();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('id="constanciaForm.calidadProfesional"')
            ->assertDontSeeHtml('id="constanciaForm.calidadProfesionalOtra"');
        $this->llenarConstancia($component, ['calidadProfesional' => ''])
            ->call('guardarConstancia')
            ->assertHasErrors(['constanciaForm.calidadProfesional']);

        $component->set('constanciaForm.calidadProfesional', 'otra')
            ->assertSeeHtml('id="constanciaForm.calidadProfesionalOtra"')
            ->call('guardarConstancia')
            ->assertHasErrors(['constanciaForm.calidadProfesionalOtra']);

        $component->set('constanciaForm.calidadProfesionalOtra', 'Perito estructurista externo')
            ->call('guardarConstancia')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('constancias_seguridad_estructural', ['calidad_profesional' => 'otra', 'calidad_profesional_otra' => 'Perito estructurista externo']);
    }

    public function test_constancia_cambiar_de_otra_a_dro_no_guarda_la_descripcion_vieja(): void
    {
        $escuela = $this->escuelaParaConstancia();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $this->llenarConstancia($component, ['calidadProfesional' => 'otra', 'calidadProfesionalOtra' => 'Perito externo'])
            ->set('constanciaForm.calidadProfesional', 'dro')
            ->call('guardarConstancia')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('constancias_seguridad_estructural', ['calidad_profesional' => 'dro', 'calidad_profesional_otra' => null]);
    }

    public function test_constancia_guarda_folio_y_fechas_del_registro_del_profesional(): void
    {
        $escuela = $this->escuelaParaConstancia();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('id="constanciaForm.folioConstancia"')
            ->assertSeeHtml('id="constanciaForm.peritoRegistroFecha"')
            ->assertSeeHtml('id="constanciaForm.peritoRegistroVencimiento"');
        $this->llenarConstancia($component, ['folioConstancia' => '  CSE-2026-001 ', 'peritoRegistroFecha' => '2026-02-10', 'peritoRegistroVencimiento' => '2027-02-09'])
            ->call('guardarConstancia')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('constancias_seguridad_estructural', ['folio_constancia' => 'CSE-2026-001', 'perito_registro_fecha' => '2026-02-10', 'perito_registro_vencimiento' => '2027-02-09']);
    }

    public function test_constancia_rechaza_vencimiento_del_registro_anterior_a_su_fecha(): void
    {
        $escuela = $this->escuelaParaConstancia();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $this->llenarConstancia($component, ['peritoRegistroFecha' => '2026-02-10', 'peritoRegistroVencimiento' => '2026-02-09'])
            ->call('guardarConstancia')
            ->assertHasErrors(['constanciaForm.peritoRegistroVencimiento']);
    }

    public function test_constancia_sin_los_obligatorios_marca_cada_campo(): void
    {
        $escuela = $this->escuelaParaConstancia();

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.constancia_seguridad_estructural', UploadedFile::fake()->create('constancia.pdf', 10, 'application/pdf'))
            ->call('guardarConstancia')
            ->assertHasErrors(['constanciaForm.fechaEmision', 'constanciaForm.peritoNombre', 'constanciaForm.calidadProfesional', 'constanciaForm.peritoRegistroDro', 'constanciaForm.tipoVigencia']);
    }

    public function test_constancia_exige_un_pdf_de_hasta_10_mb(): void
    {
        $escuela = $this->escuelaParaConstancia();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $this->llenarConstancia($component)
            ->set('archivos.constancia_seguridad_estructural', UploadedFile::fake()->create('constancia.pdf', 10241, 'application/pdf'))
            ->call('guardarConstancia')
            ->assertHasErrors(['archivos.constancia_seguridad_estructural']);

        $component->set('archivos.constancia_seguridad_estructural', UploadedFile::fake()->create('constancia.txt', 10, 'text/plain'))
            ->call('guardarConstancia')
            ->assertHasErrors(['archivos.constancia_seguridad_estructural']);
        $this->assertDatabaseCount('constancias_seguridad_estructural', 0);
    }

    public function test_constancia_con_registro_vencido_antes_de_la_emision_guarda_y_muestra_un_aviso_que_no_bloquea(): void
    {
        $escuela = $this->escuelaParaConstancia();

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $this->llenarConstancia($component, ['fechaEmision' => '2026-05-10', 'peritoRegistroVencimiento' => '2026-02-28'])
            ->call('guardarConstancia')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('constancias_seguridad_estructural', 1);
        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee('vencimiento el 28/02/2026');
    }

    public function test_dictamen_pide_autoridad_folio_y_giro_y_los_guarda(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('id="dictamenForm.autoridadExpide"')
            ->assertSeeHtml('id="dictamenForm.folioDictamen"')
            ->assertSeeHtml('id="dictamenForm.giroAutorizado"')
            ->set('archivos.dictamen_uso_suelo', UploadedFile::fake()->create('dictamen.pdf', 10, 'application/pdf'))
            ->set('dictamenForm.fechaEmision', now()->toDateString())
            ->call('guardarDictamen')
            ->assertHasErrors(['dictamenForm.autoridadExpide', 'dictamenForm.folioDictamen', 'dictamenForm.giroAutorizado']);
        $this->assertDatabaseCount('dictamenes_uso_suelo', 0);

        $component->set('dictamenForm.autoridadExpide', 'Dirección de Desarrollo Urbano')
            ->set('dictamenForm.folioDictamen', 'DUS-2026-001')
            ->set('dictamenForm.giroAutorizado', 'Escuela de educación básica')
            ->set('dictamenForm.tipoVigencia', 'sin_fecha_expresa')
            ->call('guardarDictamen')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dictamenes_uso_suelo', [
            'autoridad_expide' => 'Dirección de Desarrollo Urbano',
            'folio_dictamen' => 'DUS-2026-001',
            'giro_autorizado' => 'Escuela de educación básica',
        ]);
    }

    public function test_acreditacion_no_muestra_campo_observaciones(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertDontSeeHtml('id="acreditacionForm.observaciones"');
    }

    public function test_variante_arrendamiento_sin_datos_de_contrato_falla_validacion(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne');
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
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('comodato.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'comodato')
            ->set('acreditacionForm.arrendadorComodante', 'Comodante SA')
            ->set('acreditacionForm.arrendatarioComodatario', 'Juana Pérez')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.fechaInicioVigencia', '2026-02-01')
            ->set('acreditacionForm.usoAutorizado', 'Prestación de servicio educativo')
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
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf'))
            ->set('acreditacionForm.tipo', 'otro')
            ->call('guardarAcreditacion')
            ->assertHasErrors('acreditacionForm.otroEspecifique');
        $this->assertDatabaseCount('acreditaciones_ocupacion_legal', 0);

        $component->set('acreditacionForm.otroEspecifique', 'Posesión por resolución judicial')
            ->set('acreditacionForm.otroReferencia', 'EXP-123/2025')
            ->set('acreditacionForm.otroTitular', 'Colegio Norte SA de CV')
            ->set('acreditacionForm.otroFechaExpedicion', '2025-05-01')
            ->set('acreditacionForm.otroAutoridad', 'Juzgado Civil')
            ->set('acreditacionForm.otroDerecho', 'Posesión')
            ->set('acreditacionForm.usoAutorizado', 'Prestación de servicio educativo')
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
            ->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne')
            ->assertHasErrors('archivos.ine');

        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    public function test_guardar_un_documento_con_otros_pendientes_avisa_con_un_toast(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 50, 'application/pdf'))
            ->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne')
            ->assertDispatched('toast', tipo: 'success', titulo: 'Documento guardado');
    }

    public function test_el_ultimo_documento_deja_un_toast_para_la_pagina_siguiente_en_vez_de_despacharlo(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (array_diff(app(DocumentosCompletos::class)->clavesAplicables('fisica'), ['plano_inmueble']) as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.plano_inmueble', UploadedFile::fake()->create('plano.pdf', 10, 'application/pdf'))
            ->call('guardarDocumentoSimple', 'plano_inmueble')
            ->assertSessionHas('toast.titulo', 'Documentos completos')
            ->assertNotDispatched('toast');
    }

    public function test_si_el_caso_de_uso_rechaza_el_documento_avisa_con_un_toast_de_error(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $this->mock(RegistrarDocumento::class)->shouldReceive('ejecutar')->andThrow(new DatosInvalidos(['archivos.ine' => 'El archivo debe ser un PDF.']));

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))
            ->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó');
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
            ->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne')
            ->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));

        $this->assertDatabaseCount('documentos_escuela', 0);
    }

    // --- Acreditación de ocupación legal: persistencia por variante ---

    private function componenteListoParaAcreditar(): Testable
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $component = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
        $component->set('archivos.ine', UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'))->set('ineForm.nombre', 'PEREZ JUANA')->set('ineForm.curp', 'PEJU800101MQTRRN01')->call('guardarIne');
        $component->set('archivos.acta_nacimiento', UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'))->call('guardarDocumentoSimple', 'acta_nacimiento');

        return $component->set('archivos.escritura_inmueble', UploadedFile::fake()->create('acreditacion.pdf', 10, 'application/pdf'));
    }

    /** @return array<string, mixed> */
    private function filaAcreditacion(): array
    {
        return (array) DB::table('acreditaciones_ocupacion_legal')->first();
    }

    public function test_cambiar_de_tipo_antes_de_guardar_no_persiste_la_variante_anterior(): void
    {
        $component = $this->componenteListoParaAcreditar();

        $component->set('acreditacionForm.tipo', 'escritura_publica')
            ->set('acreditacionForm.numeroEscritura', 'E-500')
            ->set('acreditacionForm.folioRpp', 'FME-123')
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->set('acreditacionForm.arrendadorComodante', 'Juan Arrendador')
            ->set('acreditacionForm.arrendatarioComodatario', 'Juana Pérez')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.fechaInicioVigencia', '2026-02-01')
            ->set('acreditacionForm.vigenciaContrato', '2030-01-01')
            ->set('acreditacionForm.usoAutorizado', 'Prestación de servicio educativo')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();

        $fila = $this->filaAcreditacion();
        $this->assertSame('arrendamiento', $fila['tipo']);
        $this->assertNull($fila['folio_rpp']);
        $this->assertNull($fila['numero_escritura']);
    }

    public function test_escritura_guarda_propietario_y_datos_registrales(): void
    {
        $queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        $corregidora = Municipio::create(['entidad_federativa_id' => $queretaro->id, 'clave' => 6, 'nombre' => 'CORREGIDORA']);

        $this->componenteListoParaAcreditar()
            ->set('acreditacionForm.tipo', 'escritura_publica')
            ->set('acreditacionForm.numeroEscritura', 'E-500')
            ->set('acreditacionForm.fechaEscritura', '2024-03-15')
            ->set('acreditacionForm.propietarioNombre', 'Colegio Norte SA de CV')
            ->set('acreditacionForm.notarioNombre', 'Lic. Ana Notaria')
            ->set('acreditacionForm.notarioNumero', '14')
            ->set('acreditacionForm.notarioEntidadFederativaId', (string) $queretaro->id)
            ->set('acreditacionForm.notarioMunicipioId', (string) $corregidora->id)
            ->set('acreditacionForm.folioRpp', 'FME-123')
            ->set('acreditacionForm.fechaInscripcionRpp', '2024-04-01')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'escritura_publica',
            'propietario_nombre' => 'Colegio Norte SA de CV',
            'notario_numero' => '14',
            'notario_entidad_federativa_id' => $queretaro->id,
            'notario_municipio_id' => $corregidora->id,
            'folio_rpp' => 'FME-123',
            'fecha_inscripcion_rpp' => '2024-04-01',
        ]);
    }

    public function test_arrendamiento_guarda_ratificacion_y_facultades_del_arrendador(): void
    {
        $this->componenteListoParaAcreditar()
            ->set('acreditacionForm.tipo', 'arrendamiento')
            ->set('acreditacionForm.arrendadorComodante', 'Inmobiliaria Sur SA')
            ->set('acreditacionForm.arrendatarioComodatario', 'Colegio Norte SA de CV')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.fechaInicioVigencia', '2026-02-01')
            ->set('acreditacionForm.vigenciaContrato', '2030-01-31')
            ->set('acreditacionForm.usoAutorizado', 'Prestación de servicio educativo')
            ->set('acreditacionForm.ratificadoNotario', true)
            ->set('acreditacionForm.ratificacionFecha', '2026-01-20')
            ->set('acreditacionForm.ratificacionNotarioNombre', 'Lic. Ana Notaria')
            ->set('acreditacionForm.ratificacionNotariaNumero', '14')
            ->set('acreditacionForm.arrendadorEsPropietario', false)
            ->set('acreditacionForm.facultadesReferencia', 'Poder notarial 123')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();

        $fila = $this->filaAcreditacion();
        $this->assertTrue($fila['ratificado_notario']);
        $this->assertSame('2026-01-20', $fila['ratificacion_fecha']);
        $this->assertSame('Lic. Ana Notaria', $fila['ratificacion_notario_nombre']);
        $this->assertSame('14', $fila['ratificacion_notaria_numero']);
        $this->assertFalse($fila['arrendador_es_propietario']);
        $this->assertSame('Poder notarial 123', $fila['facultades_referencia']);
    }

    public function test_comodato_con_vigencia_indeterminada_guarda_fin_nulo(): void
    {
        $this->componenteListoParaAcreditar()
            ->set('acreditacionForm.tipo', 'comodato')
            ->set('acreditacionForm.arrendadorComodante', 'Comodante SA')
            ->set('acreditacionForm.arrendatarioComodatario', 'Colegio Norte SA de CV')
            ->set('acreditacionForm.fechaContrato', '2026-01-01')
            ->set('acreditacionForm.fechaInicioVigencia', '2026-02-01')
            ->set('acreditacionForm.vigenciaContrato', '2030-01-31')
            ->set('acreditacionForm.vigenciaIndeterminada', true)
            ->set('acreditacionForm.usoAutorizado', 'Prestación de servicio educativo')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();

        $fila = $this->filaAcreditacion();
        $this->assertTrue($fila['vigencia_indeterminada']);
        $this->assertNull($fila['vigencia_contrato']);
    }

    public function test_otro_guarda_los_metadatos_del_instrumento(): void
    {
        $this->componenteListoParaAcreditar()
            ->set('acreditacionForm.tipo', 'otro')
            ->set('acreditacionForm.otroEspecifique', 'Resolución judicial de posesión')
            ->set('acreditacionForm.otroReferencia', 'EXP-123/2025')
            ->set('acreditacionForm.otroTitular', 'Colegio Norte SA de CV')
            ->set('acreditacionForm.otroFechaExpedicion', '2025-05-01')
            ->set('acreditacionForm.otroAutoridad', 'Juzgado Civil')
            ->set('acreditacionForm.otroDerecho', 'Posesión')
            ->set('acreditacionForm.usoAutorizado', 'Ocupación para actividades escolares')
            ->call('guardarAcreditacion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('acreditaciones_ocupacion_legal', [
            'tipo' => 'otro',
            'otro_referencia' => 'EXP-123/2025',
            'otro_titular' => 'Colegio Norte SA de CV',
            'otro_fecha_expedicion' => '2025-05-01',
            'otro_autoridad' => 'Juzgado Civil',
            'otro_derecho' => 'Posesión',
            'uso_autorizado' => 'Ocupación para actividades escolares',
        ]);
    }

    // --- Dictamen de Uso de Suelo: vigencia declarada por el documento ---

    private function componenteListoParaDictamen(): Testable
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $registrar = new RegistrarDocumento;
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble'] as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        return Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivos.dictamen_uso_suelo', UploadedFile::fake()->create('dictamen.pdf', 10, 'application/pdf'))
            ->set('dictamenForm.fechaEmision', now()->subDays(90)->toDateString())
            ->set('dictamenForm.autoridadExpide', 'Dirección de Desarrollo Urbano')
            ->set('dictamenForm.folioDictamen', 'DUS-2026-001')
            ->set('dictamenForm.giroAutorizado', 'Escuela de educación básica');
    }

    public function test_dictamen_con_fecha_expresa_guarda_vencimiento_y_condicionantes_sin_fecha_calculada(): void
    {
        $this->componenteListoParaDictamen()
            ->set('dictamenForm.tipoVigencia', 'fecha_expresa')
            ->set('dictamenForm.fechaVencimientoDocumento', now()->addYear()->toDateString())
            ->set('dictamenForm.condicionantes', 'Máximo 200 alumnos por turno.')
            ->call('guardarDictamen')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dictamenes_uso_suelo', [
            'tipo_vigencia' => 'fecha_expresa',
            'fecha_vencimiento_documento' => now()->addYear()->toDateString(),
            'condicionantes' => 'Máximo 200 alumnos por turno.',
            'giro_autorizado' => 'Escuela de educación básica',
        ]);
        $this->assertNull(DB::table('documentos_plantel')->value('fecha_vigencia'));
    }

    public function test_dictamen_sin_fecha_expresa_no_inventa_una_fecha_aunque_se_emitio_hace_90_dias(): void
    {
        $this->componenteListoParaDictamen()
            ->set('dictamenForm.tipoVigencia', 'sin_fecha_expresa')
            ->call('guardarDictamen')
            ->assertHasNoErrors();

        $fila = (array) DB::table('dictamenes_uso_suelo')->first();
        $this->assertSame('sin_fecha_expresa', $fila['tipo_vigencia']);
        $this->assertNull($fila['fecha_vencimiento_documento']);
        $this->assertNull(DB::table('documentos_plantel')->value('fecha_vigencia'));
    }

    public function test_dictamen_muestra_los_campos_de_vigencia_declarada_y_la_ayuda(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee('Uso o giro autorizado según el dictamen')
            ->assertSee('Capture el uso autorizado tal como aparece en el documento emitido por la autoridad municipal.')
            ->assertSee('Sí, el documento indica una fecha de vencimiento')
            ->assertSee('No, el documento no indica fecha de vencimiento')
            ->assertSee('Registre solo lo que dice el documento')
            ->assertSeeHtml('id="dictamenForm.condicionantes"')
            ->assertSee('Condicionantes')
            ->assertDontSeeHtml('id="dictamenForm.fechaVencimientoDocumento"');
    }

    public function test_dictamen_muestra_la_fecha_de_vencimiento_solo_si_el_documento_la_indica(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('dictamenForm.tipoVigencia', 'fecha_expresa')
            ->assertSeeHtml('id="dictamenForm.fechaVencimientoDocumento"')
            ->set('dictamenForm.tipoVigencia', 'sin_fecha_expresa')
            ->assertDontSeeHtml('id="dictamenForm.fechaVencimientoDocumento"');
    }

    // --- Ubicación de la notaría: selectores alimentados por el catálogo geográfico ---

    public function test_los_selectores_de_la_notaria_salen_de_la_base_de_datos_y_no_de_la_vista(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        $vacio = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSeeHtml('id="acreditacionForm.notarioEntidadFederativaId"')
            ->assertSeeHtml('id="acreditacionForm.notarioMunicipioId"')
            ->assertDontSee('QUERÉTARO')
            ->assertSee('catálogo geográfico');

        $this->assertStringNotContainsString('AGUASCALIENTES', $vacio->html());

        EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        EntidadFederativa::create(['clave' => 1, 'nombre' => 'AGUASCALIENTES']);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee('QUERÉTARO')
            ->assertSee('AGUASCALIENTES')
            ->assertDontSee('catálogo geográfico');
    }

    public function test_los_municipios_siguen_a_la_entidad_elegida_y_el_selector_empieza_deshabilitado(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        $otra = EntidadFederativa::create(['clave' => 1, 'nombre' => 'AGUASCALIENTES']);
        Municipio::create(['entidad_federativa_id' => $queretaro->id, 'clave' => 6, 'nombre' => 'CORREGIDORA']);
        Municipio::create(['entidad_federativa_id' => $otra->id, 'clave' => 2, 'nombre' => 'ASIENTOS']);

        $prueba = Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertDontSee('CORREGIDORA')
            ->assertDontSee('ASIENTOS');
        $this->assertMatchesRegularExpression('/<select[^>]*id="acreditacionForm.notarioMunicipioId"[^>]*\bdisabled\b/s', $prueba->html());

        $prueba->set('acreditacionForm.notarioEntidadFederativaId', (string) $queretaro->id)
            ->assertSee('CORREGIDORA')
            ->assertDontSee('ASIENTOS');
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*id="acreditacionForm.notarioMunicipioId"[^>]*\bdisabled\b/s', $prueba->html());
    }

    public function test_cambiar_la_entidad_borra_el_municipio_elegido(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);
        $queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        $otra = EntidadFederativa::create(['clave' => 1, 'nombre' => 'AGUASCALIENTES']);
        $corregidora = Municipio::create(['entidad_federativa_id' => $queretaro->id, 'clave' => 6, 'nombre' => 'CORREGIDORA']);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('acreditacionForm.notarioEntidadFederativaId', (string) $queretaro->id)
            ->set('acreditacionForm.notarioMunicipioId', (string) $corregidora->id)
            ->set('acreditacionForm.notarioEntidadFederativaId', (string) $otra->id)
            ->assertSet('acreditacionForm.notarioMunicipioId', '')
            ->assertHasNoErrors(['acreditacionForm.notarioMunicipioId']);
    }
}
