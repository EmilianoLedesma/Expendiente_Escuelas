<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\Aplicabilidad;
use App\Application\Documentos\EvaluarAplicabilidadDocumentos;
use App\Application\Documentos\ResultadoAplicabilidad;
use App\Domain\Documentos\ComparadorDomicilios;
use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use App\Livewire\Tramite\Paso2Documentos;
use App\Models\CertificadoNumeroOficial;
use App\Models\DocumentoPlantel;
use App\Models\EntidadFederativa;
use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/**
 * Captura del Certificado de Número Oficial en Paso 2.2 (spec 2026-10-20 §3 y §9): el domicilio es lo que dice
 * el documento; autoridad, folio, fecha de expedición, número interior y clave catastral son opcionales.
 */
class Paso2NumeroOficialTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private EntidadFederativa $queretaro;

    private EntidadFederativa $otra;

    private Municipio $corregidora;

    private Municipio $asientos;

    private function componente(): Testable
    {
        Storage::fake('documentos');
        $this->queretaro = EntidadFederativa::create(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        $this->otra = EntidadFederativa::create(['clave' => 1, 'nombre' => 'AGUASCALIENTES']);
        $this->corregidora = Municipio::create(['entidad_federativa_id' => $this->queretaro->id, 'clave' => 6, 'nombre' => 'CORREGIDORA']);
        $this->asientos = Municipio::create(['entidad_federativa_id' => $this->otra->id, 'clave' => 2, 'nombre' => 'ASIENTOS']);
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela);
        $this->actingAs($escuela->solicitante->user);

        return Livewire::test(Paso2Documentos::class, ['escuela' => $escuela]);
    }

    /** @param  array<string, string>  $campos */
    private function llenar(Testable $componente, array $campos = []): Testable
    {
        $valores = [
            'calle' => 'Av. Juárez', 'numeroExt' => '12', 'colonia' => 'Centro', 'codigoPostal' => '76000',
            'entidadFederativaId' => (string) $this->queretaro->id, 'municipioId' => (string) $this->corregidora->id,
            ...$campos,
        ];
        $componente->set('archivos.certificado_numero_oficial', UploadedFile::fake()->create('cert.pdf', 10, 'application/pdf'));
        foreach ($valores as $campo => $valor) {
            $componente->set("numeroOficialForm.{$campo}", $valor);
        }

        return $componente;
    }

    public function test_muestra_los_campos_del_certificado_incluidos_los_nuevos(): void
    {
        $componente = $this->componente();

        foreach (['calle', 'numeroExt', 'numeroInterior', 'colonia', 'entidadFederativaId', 'municipioId', 'codigoPostal', 'fechaExpedicion', 'autoridadEmisora', 'folioCertificado', 'claveCatastral'] as $campo) {
            $componente->assertSeeHtml("id=\"numeroOficialForm.{$campo}\"");
        }
    }

    public function test_guarda_los_metadatos_y_la_fecha_de_expedicion_sin_inventar_vencimiento(): void
    {
        $componente = $this->componente();

        $this->llenar($componente, [
            'numeroInterior' => 'A-3', 'fechaExpedicion' => '2026-03-15', 'autoridadEmisora' => 'Dirección de Atención a Fraccionamientos e Inmobiliarias',
            'folioCertificado' => 'CNO-2026-0001', 'claveCatastral' => '14 01 001 01 001',
        ])->call('guardarNumeroOficial')->assertHasNoErrors();

        $fila = CertificadoNumeroOficial::sole();
        $this->assertSame('A-3', $fila->numero_int);
        $this->assertSame('CNO-2026-0001', $fila->folio_certificado);
        $this->assertSame('Dirección de Atención a Fraccionamientos e Inmobiliarias', $fila->autoridad_emisora);
        $this->assertSame('14 01 001 01 001', $fila->clave_catastral);
        $this->assertDatabaseHas('documentos_plantel', ['fecha_emision' => '2026-03-15', 'fecha_vigencia' => null]);
    }

    public function test_los_campos_nuevos_son_opcionales(): void
    {
        $componente = $this->componente();

        $this->llenar($componente)->call('guardarNumeroOficial')->assertHasNoErrors();

        $fila = CertificadoNumeroOficial::sole();
        $this->assertNull($fila->numero_int);
        $this->assertNull($fila->autoridad_emisora);
        $this->assertNull($fila->folio_certificado);
        $this->assertNull($fila->clave_catastral);
        $this->assertDatabaseHas('documentos_plantel', ['fecha_emision' => null, 'fecha_vigencia' => null]);
    }

    public function test_un_inmueble_sin_numero_se_captura_como_s_n(): void
    {
        $componente = $this->componente();

        $this->llenar($componente, ['numeroExt' => 'S/N'])->call('guardarNumeroOficial')->assertHasNoErrors();

        $this->assertSame('S/N', CertificadoNumeroOficial::sole()->numero_ext);
    }

    public function test_normaliza_los_espacios_de_los_campos_de_texto(): void
    {
        $componente = $this->componente();

        $this->llenar($componente, ['autoridadEmisora' => '  Dirección   de Desarrollo  Urbano ', 'folioCertificado' => '  CNO 1  '])
            ->call('guardarNumeroOficial')->assertHasNoErrors();

        $fila = CertificadoNumeroOficial::sole();
        $this->assertSame('Dirección de Desarrollo Urbano', $fila->autoridad_emisora);
        $this->assertSame('CNO 1', $fila->folio_certificado);
    }

    public function test_rechaza_datos_invalidos_sin_guardar_nada(): void
    {
        $componente = $this->componente();

        $this->llenar($componente, ['fechaExpedicion' => now()->addDay()->toDateString()])->call('guardarNumeroOficial')->assertHasErrors(['numeroOficialForm.fechaExpedicion']);
        $componente->set('numeroOficialForm.fechaExpedicion', '')->set('numeroOficialForm.codigoPostal', '760')->call('guardarNumeroOficial')->assertHasErrors(['numeroOficialForm.codigoPostal']);
        $componente->set('numeroOficialForm.codigoPostal', '76000')
            ->set('numeroOficialForm.autoridadEmisora', str_repeat('x', 201))
            ->set('numeroOficialForm.folioCertificado', str_repeat('F', 51))
            ->set('numeroOficialForm.numeroInterior', str_repeat('1', 21))
            ->set('numeroOficialForm.claveCatastral', str_repeat('9', 41))
            ->call('guardarNumeroOficial')
            ->assertHasErrors(['numeroOficialForm.autoridadEmisora', 'numeroOficialForm.folioCertificado', 'numeroOficialForm.numeroInterior', 'numeroOficialForm.claveCatastral']);

        $this->assertDatabaseCount('certificados_numero_oficial', 0);
    }

    public function test_exige_los_datos_minimos_del_domicilio(): void
    {
        $componente = $this->componente();

        $componente->set('archivos.certificado_numero_oficial', UploadedFile::fake()->create('cert.pdf', 10, 'application/pdf'))
            ->call('guardarNumeroOficial')
            ->assertHasErrors(['numeroOficialForm.calle', 'numeroOficialForm.colonia', 'numeroOficialForm.entidadFederativaId', 'numeroOficialForm.municipioId', 'numeroOficialForm.codigoPostal']);
    }

    public function test_entidad_y_municipio_son_listas_del_catalogo_y_el_municipio_sigue_a_la_entidad(): void
    {
        $componente = $this->componente();

        $componente->assertSee('QUERÉTARO')->assertSee('AGUASCALIENTES')->assertDontSee('CORREGIDORA')->assertDontSee('ASIENTOS')
            ->assertDontSeeHtml('wire:model.blur="numeroOficialForm.municipio"');
        $componente->set('numeroOficialForm.entidadFederativaId', (string) $this->queretaro->id)->assertSee('CORREGIDORA')->assertDontSee('ASIENTOS');
    }

    public function test_cambiar_la_entidad_limpia_el_municipio_elegido(): void
    {
        $componente = $this->componente();

        $componente->set('numeroOficialForm.entidadFederativaId', (string) $this->queretaro->id)
            ->set('numeroOficialForm.municipioId', (string) $this->corregidora->id)
            ->set('numeroOficialForm.entidadFederativaId', (string) $this->otra->id)
            ->assertSet('numeroOficialForm.municipioId', '');
    }

    public function test_guarda_las_referencias_y_el_nombre_del_municipio_sale_del_catalogo(): void
    {
        $componente = $this->componente();

        $this->llenar($componente)->call('guardarNumeroOficial')->assertHasNoErrors();

        $fila = CertificadoNumeroOficial::sole();
        $this->assertSame($this->queretaro->id, $fila->entidad_federativa_id);
        $this->assertSame($this->corregidora->id, $fila->municipio_id);
        $this->assertSame('CORREGIDORA', $fila->municipio);
    }

    public function test_rechaza_un_municipio_que_no_pertenece_a_la_entidad(): void
    {
        $componente = $this->componente();

        $this->llenar($componente, ['municipioId' => (string) $this->asientos->id])->call('guardarNumeroOficial')->assertHasErrors(['numeroOficialForm.municipioId']);
        $this->assertDatabaseCount('certificados_numero_oficial', 0);
    }

    public function test_colonia_y_codigo_postal_siguen_siendo_texto(): void
    {
        $componente = $this->componente();

        $componente->assertSeeHtml('wire:model.blur="numeroOficialForm.colonia"')->assertSeeHtml('wire:model.blur="numeroOficialForm.codigoPostal"');
    }

    public function test_exige_un_pdf_de_hasta_10_mb(): void
    {
        $componente = $this->componente();

        $this->llenar($componente)
            ->set('archivos.certificado_numero_oficial', UploadedFile::fake()->create('cert.pdf', 10241, 'application/pdf'))
            ->call('guardarNumeroOficial')
            ->assertHasErrors(['archivos.certificado_numero_oficial']);
        $componente->set('archivos.certificado_numero_oficial', UploadedFile::fake()->create('cert.txt', 10, 'text/plain'))
            ->call('guardarNumeroOficial')
            ->assertHasErrors(['archivos.certificado_numero_oficial']);
        $this->assertDatabaseCount('documentos_plantel', 0);
    }

    private function fijarAplicabilidad(Aplicabilidad $estado): void
    {
        $this->app->bind(EvaluarAplicabilidadDocumentos::class, fn () => new class(new ComparadorDomicilios(new NormalizadorDomicilio), $estado) extends EvaluarAplicabilidadDocumentos
        {
            public function __construct(ComparadorDomicilios $comparador, private Aplicabilidad $estado)
            {
                parent::__construct($comparador);
            }

            public function condicionales(int $escuelaId): array
            {
                return [self::CERTIFICADO_NUMERO_OFICIAL => new ResultadoAplicabilidad($this->estado, 'prueba', 'prueba', 'prueba')];
            }
        });
    }

    public function test_por_defecto_no_aparece_ninguna_nota_de_documento_no_obligatorio(): void
    {
        $this->componente()->assertDontSee('no es obligatorio');
    }

    public function test_si_el_certificado_no_es_requerido_se_avisa_pero_se_puede_cargar_y_no_cuenta_para_el_avance(): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::NoRequerido);
        $componente = $this->componente();

        $componente->assertSee('no es obligatorio')->assertSeeHtml('id="numeroOficialForm.calle"');
        $this->llenar($componente)->call('guardarNumeroOficial')->assertHasNoErrors();
        $this->assertDatabaseCount('certificados_numero_oficial', 1);
    }

    public function test_el_solicitante_no_tiene_forma_de_declarar_el_certificado_no_requerido(): void
    {
        $componente = $this->componente();

        $this->expectException(\Throwable::class);
        $componente->set('aplicabilidad', ['certificado_numero_oficial' => 'no_requerido']);
    }

    public function test_un_reemplazo_invalido_conserva_el_certificado_anterior(): void
    {
        $componente = $this->componente();
        $this->llenar($componente, ['folioCertificado' => 'CNO-1'])->call('guardarNumeroOficial')->assertHasNoErrors();
        $ruta = DocumentoPlantel::first()->archivo_path;

        $componente->call('toggleReemplazar', 'certificado_numero_oficial');
        $this->llenar($componente, ['folioCertificado' => 'CNO-2', 'fechaExpedicion' => now()->addDay()->toDateString()])
            ->call('guardarNumeroOficial')
            ->assertHasErrors(['numeroOficialForm.fechaExpedicion']);

        $this->assertSame($ruta, DocumentoPlantel::first()->archivo_path);
        Storage::disk('documentos')->assertExists($ruta);
        $this->assertSame('CNO-1', CertificadoNumeroOficial::sole()->folio_certificado);
    }
}
