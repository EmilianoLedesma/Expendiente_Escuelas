<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use App\Models\VistoBuenoProteccionCivil;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Visto Bueno de Protección Civil: el caso de uso impone las invariantes (spec 2026-10-20 §4)
 * con o sin Livewire, y solo el Visto Bueno del Municipio de Querétaro con ejercicio capturado
 * recibe un vencimiento normativo (Reglamento PC 2017, art. 46).
 */
class RegistrarProteccionCivilTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
    }

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        ResponsableLegal::create(['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);

        return $escuela;
    }

    /**
     * Captura completa válida (Visto Bueno del Municipio de Querétaro, ejercicio 2026).
     *
     * @param  array<string, mixed>  $campos
     */
    private function datos(array $campos = []): DatosDocumento
    {
        return new DatosDocumento(...[
            'fechaEmision' => '2026-02-10',
            'tipoInstrumento' => 'visto_bueno',
            'autoridadEmisora' => 'Coordinación Municipal de Protección Civil',
            'jurisdiccionEmisora' => 'municipio_queretaro',
            'folioDocumento' => 'VB-2026-001',
            'clasificacionRiesgo' => 'alto',
            'ejercicio' => 2026,
            'tipoVigencia' => 'sin_fecha_expresa',
            'condicionantes' => 'Aforo máximo 200 personas.',
            ...$campos,
        ]);
    }

    private function registrar(Escuela $escuela, DatosDocumento $datos, string $nombre = 'pc.pdf'): void
    {
        (new RegistrarDocumento)->ejecutar($escuela->id, 'visto_bueno_proteccion_civil', UploadedFile::fake()->create($nombre, 10, 'application/pdf'), $datos);
    }

    private function extension(Escuela $escuela): VistoBuenoProteccionCivil
    {
        return VistoBuenoProteccionCivil::query()
            ->whereIn('documento_plantel_id', DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->select('id'))
            ->firstOrFail();
    }

    public function test_persiste_la_captura_completa_del_visto_bueno_de_queretaro_con_su_vencimiento_normativo(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos());

        $fila = $this->extension($escuela);
        $this->assertSame('visto_bueno', $fila->tipo_instrumento);
        $this->assertSame('Coordinación Municipal de Protección Civil', $fila->autoridad_emisora);
        $this->assertSame('municipio_queretaro', $fila->jurisdiccion_emisora);
        $this->assertSame('VB-2026-001', $fila->folio_documento);
        $this->assertSame('alto', $fila->clasificacion_riesgo);
        $this->assertSame(2026, $fila->ejercicio);
        $this->assertSame('sin_fecha_expresa', $fila->tipo_vigencia);
        $this->assertNull($fila->fecha_vencimiento_documento);
        $this->assertSame('reglamento_pc_queretaro_2017', $fila->regimen_normativo);
        $this->assertSame('2026-12-31', $fila->fecha_vencimiento_normativo);
        $this->assertSame('Aforo máximo 200 personas.', $fila->condicionantes);
        $this->assertDatabaseHas('documentos_plantel', ['plantel_id' => $escuela->plantel_id, 'fecha_emision' => '2026-02-10', 'fecha_vigencia' => null]);
    }

    public function test_no_calcula_ninguna_fecha_a_30_dias(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['jurisdiccionEmisora' => 'otra', 'ejercicio' => null]));

        $this->assertDatabaseHas('documentos_plantel', ['plantel_id' => $escuela->plantel_id, 'fecha_vigencia' => null]);
    }

    public function test_el_vencimiento_declarado_se_guarda_aparte_del_normativo(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['tipoVigencia' => 'fecha_expresa', 'fechaVencimientoDocumento' => '2026-06-30']));

        $fila = $this->extension($escuela);
        $this->assertSame('2026-06-30', $fila->fecha_vencimiento_documento);
        $this->assertSame('2026-12-31', $fila->fecha_vencimiento_normativo);
    }

    public function test_sin_fecha_expresa_anula_el_vencimiento_aunque_el_llamador_mande_uno(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['tipoVigencia' => 'sin_fecha_expresa', 'fechaVencimientoDocumento' => '2026-06-30']));

        $this->assertNull($this->extension($escuela)->fecha_vencimiento_documento);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function casosSinVencimientoNormativo(): array
    {
        return [
            'otra autoridad' => [['jurisdiccionEmisora' => 'otra', 'ejercicio' => 2026]],
            'dictamen de Querétaro' => [['tipoInstrumento' => 'dictamen']],
            'otro instrumento de Querétaro' => [['tipoInstrumento' => 'otro', 'tipoInstrumentoOtro' => 'Opinión técnica']],
            'visto bueno de Querétaro sin ejercicio' => [['ejercicio' => null]],
        ];
    }

    /** El ejercicio solo se guarda cuando alimenta la regla del art. 46: nada más lo conserva. @param  array<string, mixed>  $campos */
    #[DataProvider('casosSinVencimientoNormativo')]
    public function test_la_regla_del_31_de_diciembre_se_limita_al_visto_bueno_de_queretaro_con_ejercicio(array $campos): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos($campos));

        $fila = $this->extension($escuela);
        $this->assertNull($fila->fecha_vencimiento_normativo);
        $this->assertNull($fila->ejercicio);
    }

    public function test_el_ejercicio_no_se_infiere_de_la_fecha_de_emision(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['fechaEmision' => '2026-02-10', 'ejercicio' => null]));

        $this->assertNull($this->extension($escuela)->ejercicio);
        $this->assertNull($this->extension($escuela)->fecha_vencimiento_normativo);
    }

    public function test_el_ejercicio_puede_ser_el_del_ano_siguiente_a_la_emision(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['fechaEmision' => '2026-10-15', 'ejercicio' => 2027]));

        $this->assertSame('2027-12-31', $this->extension($escuela)->fecha_vencimiento_normativo);
    }

    public function test_instrumento_otro_guarda_su_descripcion_y_cualquier_otro_la_anula(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['tipoInstrumento' => 'otro', 'tipoInstrumentoOtro' => 'Opinión técnica']));
        $this->assertSame('Opinión técnica', $this->extension($escuela)->tipo_instrumento_otro);

        $this->registrar($escuela, $this->datos(['tipoInstrumento' => 'dictamen', 'tipoInstrumentoOtro' => 'Opinión técnica']), 'pc2.pdf');
        $this->assertNull($this->extension($escuela)->tipo_instrumento_otro);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function entradasInvalidas(): array
    {
        return [
            'emisión futura' => [['fechaEmision' => '2999-01-01', 'ejercicio' => null], 'proteccionCivilForm.fechaEmision'],
            'emisión ausente' => [['fechaEmision' => null], 'proteccionCivilForm.fechaEmision'],
            'emisión con formato inválido' => [['fechaEmision' => '10/02/2026'], 'proteccionCivilForm.fechaEmision'],
            'instrumento ausente' => [['tipoInstrumento' => null], 'proteccionCivilForm.tipoInstrumento'],
            'instrumento desconocido' => [['tipoInstrumento' => 'certificado'], 'proteccionCivilForm.tipoInstrumento'],
            'otro sin descripción' => [['tipoInstrumento' => 'otro', 'tipoInstrumentoOtro' => null], 'proteccionCivilForm.tipoInstrumentoOtro'],
            'otro con descripción en blanco' => [['tipoInstrumento' => 'otro', 'tipoInstrumentoOtro' => '   '], 'proteccionCivilForm.tipoInstrumentoOtro'],
            'otro con descripción muy larga' => [['tipoInstrumento' => 'otro', 'tipoInstrumentoOtro' => str_repeat('x', 151)], 'proteccionCivilForm.tipoInstrumentoOtro'],
            'autoridad ausente' => [['autoridadEmisora' => null], 'proteccionCivilForm.autoridadEmisora'],
            'autoridad en blanco' => [['autoridadEmisora' => '   '], 'proteccionCivilForm.autoridadEmisora'],
            'autoridad muy larga' => [['autoridadEmisora' => str_repeat('x', 201)], 'proteccionCivilForm.autoridadEmisora'],
            'jurisdicción ausente' => [['jurisdiccionEmisora' => null], 'proteccionCivilForm.jurisdiccionEmisora'],
            'jurisdicción desconocida' => [['jurisdiccionEmisora' => 'corregidora'], 'proteccionCivilForm.jurisdiccionEmisora'],
            'folio demasiado largo' => [['folioDocumento' => str_repeat('F', 51)], 'proteccionCivilForm.folioDocumento'],
            'riesgo ausente' => [['clasificacionRiesgo' => null], 'proteccionCivilForm.clasificacionRiesgo'],
            'riesgo desconocido' => [['clasificacionRiesgo' => 'critico'], 'proteccionCivilForm.clasificacionRiesgo'],
            'tipo de vigencia desconocido' => [['tipoVigencia' => 'anual'], 'proteccionCivilForm.tipoVigencia'],
            'fecha expresa sin fecha' => [['tipoVigencia' => 'fecha_expresa', 'fechaVencimientoDocumento' => null], 'proteccionCivilForm.fechaVencimientoDocumento'],
            'vencimiento anterior a la emisión' => [['tipoVigencia' => 'fecha_expresa', 'fechaVencimientoDocumento' => '2026-02-09'], 'proteccionCivilForm.fechaVencimientoDocumento'],
            'vencimiento con formato inválido' => [['tipoVigencia' => 'fecha_expresa', 'fechaVencimientoDocumento' => 'fin de año'], 'proteccionCivilForm.fechaVencimientoDocumento'],
            'ejercicio anterior a 2017' => [['ejercicio' => 2016], 'proteccionCivilForm.ejercicio'],
            'ejercicio anterior al año de emisión' => [['fechaEmision' => '2026-02-10', 'ejercicio' => 2025], 'proteccionCivilForm.ejercicio'],
            'ejercicio de dentro de más de un año' => [['ejercicio' => 2999], 'proteccionCivilForm.ejercicio'],
            'condicionantes demasiado largas' => [['condicionantes' => str_repeat('c', 1001)], 'proteccionCivilForm.condicionantes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_entrada_invalida_sin_dejar_nada(array $campos, string $rutaDeError): void
    {
        $escuela = $this->crearEscuela();

        try {
            $this->registrar($escuela, $this->datos($campos));
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos $e) {
            $this->assertArrayHasKey($rutaDeError, $e->errores);
        }

        $this->assertDatabaseCount('documentos_plantel', 0);
        $this->assertDatabaseCount('vistos_buenos_proteccion_civil', 0);
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_el_vencimiento_el_mismo_dia_de_la_emision_es_valido(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['tipoVigencia' => 'fecha_expresa', 'fechaVencimientoDocumento' => '2026-02-10']));

        $this->assertSame('2026-02-10', $this->extension($escuela)->fecha_vencimiento_documento);
    }

    public function test_un_llamador_anterior_solo_con_fecha_de_emision_sigue_funcionando(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, new DatosDocumento(fechaEmision: '2026-02-10'));

        $fila = $this->extension($escuela);
        $this->assertSame('no_capturada', $fila->tipo_vigencia);
        $this->assertNull($fila->clasificacion_riesgo);
        $this->assertNull($fila->fecha_vencimiento_normativo);
        $this->assertDatabaseHas('documentos_plantel', ['plantel_id' => $escuela->plantel_id, 'fecha_emision' => '2026-02-10', 'fecha_vigencia' => null]);
    }

    public function test_un_reemplazo_invalido_conserva_el_documento_anterior(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos(), 'v1.pdf');
        $rutaAnterior = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path');

        try {
            $this->registrar($escuela, $this->datos(['autoridadEmisora' => '   ', 'folioDocumento' => 'NUEVO']), 'v2.pdf');
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos) {
            // esperado
        }

        $this->assertSame($rutaAnterior, DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path'));
        Storage::disk('documentos')->assertExists($rutaAnterior);
        $this->assertSame('VB-2026-001', $this->extension($escuela)->folio_documento);
        $this->assertCount(1, Storage::disk('documentos')->allFiles("plantel/{$escuela->plantel_id}"));
    }

    public function test_reemplazar_sobrescribe_toda_la_extension(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos(['condicionantes' => 'Aforo máximo 200 personas.']));

        $this->registrar($escuela, $this->datos([
            'jurisdiccionEmisora' => 'otra', 'ejercicio' => null, 'folioDocumento' => null,
            'condicionantes' => null, 'clasificacionRiesgo' => 'no_indicado',
        ]), 'pc2.pdf');

        $fila = $this->extension($escuela);
        $this->assertSame(1, VistoBuenoProteccionCivil::count());
        $this->assertNull($fila->folio_documento);
        $this->assertNull($fila->condicionantes);
        $this->assertNull($fila->regimen_normativo);
        $this->assertNull($fila->fecha_vencimiento_normativo);
        $this->assertNull($fila->fecha_calculada_30_dias_historica);
        $this->assertSame('no_indicado', $fila->clasificacion_riesgo);
    }

    public function test_un_fallo_dentro_de_la_transaccion_conserva_archivo_y_extension_previos(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos(), 'v1.pdf');
        $rutaAnterior = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path');

        VistoBuenoProteccionCivil::saving(fn () => throw new \RuntimeException('fallo simulado'));
        try {
            $this->registrar($escuela, $this->datos(['folioDocumento' => 'NUEVO']), 'v2.pdf');
            $this->fail('Se esperaba el fallo simulado.');
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertSame($rutaAnterior, DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path'));
        Storage::disk('documentos')->assertExists($rutaAnterior);
        $this->assertSame('VB-2026-001', $this->extension($escuela)->folio_documento);
        $this->assertCount(1, Storage::disk('documentos')->allFiles("plantel/{$escuela->plantel_id}"));
    }

    public function test_no_toca_el_documento_de_otro_plantel(): void
    {
        $propia = $this->crearEscuela();
        $ajena = $this->crearEscuela();
        $this->registrar($ajena, $this->datos(['folioDocumento' => 'AJENO']));

        $this->registrar($propia, $this->datos(['folioDocumento' => 'PROPIO']));

        $this->assertSame('AJENO', $this->extension($ajena)->folio_documento);
        $this->assertSame('PROPIO', $this->extension($propia)->folio_documento);
    }
}
