<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Models\CertificadoNumeroOficial;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CompletaPaso2;
use Tests\TestCase;

/**
 * Certificado de Número Oficial: el caso de uso captura y valida los metadatos (spec 2026-10-20 §3-4)
 * con o sin Livewire. La vigencia es permanente (cédula TR-170200-013, Municipio de Querétaro): no se
 * calcula ni se inventa ningún vencimiento.
 */
class RegistrarNumeroOficialTest extends TestCase
{
    use CompletaPaso2;
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
        $this->elegirNivel($escuela->id);

        return $escuela;
    }

    /** @param  array<string, mixed>  $campos */
    private function datos(array $campos = []): DatosDocumento
    {
        return new DatosDocumento(...[
            'domicilioCalle' => 'Hidalgo', 'domicilioNumeroExt' => '12', 'domicilioColonia' => 'Centro',
            'domicilioMunicipio' => 'Querétaro', 'domicilioCodigoPostal' => '76000',
            'fechaEmision' => '2026-03-15', 'autoridadEmisora' => 'Dirección de Atención a Fraccionamientos e Inmobiliarias',
            'folioDocumento' => 'CNO-2026-0001', 'domicilioNumeroInt' => 'A-3', 'claveCatastral' => '14 01 001 01 001',
            ...$campos,
        ]);
    }

    private function registrar(Escuela $escuela, DatosDocumento $datos, string $nombre = 'cert.pdf'): void
    {
        (new RegistrarDocumento)->ejecutar($escuela->id, 'certificado_numero_oficial', UploadedFile::fake()->create($nombre, 10, 'application/pdf'), $datos);
    }

    private function fila(Escuela $escuela): CertificadoNumeroOficial
    {
        return CertificadoNumeroOficial::query()
            ->whereIn('documento_plantel_id', DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->select('id'))
            ->firstOrFail();
    }

    public function test_persiste_los_metadatos_y_la_fecha_de_expedicion_sin_inventar_vencimiento(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos());

        $fila = $this->fila($escuela);
        $this->assertSame('Hidalgo', $fila->calle);
        $this->assertSame('A-3', $fila->numero_int);
        $this->assertSame('Dirección de Atención a Fraccionamientos e Inmobiliarias', $fila->autoridad_emisora);
        $this->assertSame('CNO-2026-0001', $fila->folio_certificado);
        $this->assertSame('14 01 001 01 001', $fila->clave_catastral);
        $this->assertDatabaseHas('documentos_plantel', ['plantel_id' => $escuela->plantel_id, 'fecha_emision' => '2026-03-15', 'fecha_vigencia' => null]);
    }

    public function test_los_metadatos_nuevos_son_opcionales(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, new DatosDocumento(domicilioCalle: 'Hidalgo', domicilioColonia: 'Centro', domicilioMunicipio: 'Querétaro', domicilioCodigoPostal: '76000'));

        $fila = $this->fila($escuela);
        $this->assertNull($fila->numero_int);
        $this->assertNull($fila->autoridad_emisora);
        $this->assertNull($fila->folio_certificado);
        $this->assertNull($fila->clave_catastral);
        $this->assertDatabaseHas('documentos_plantel', ['plantel_id' => $escuela->plantel_id, 'fecha_emision' => null, 'fecha_vigencia' => null]);
    }

    public function test_un_inmueble_sin_numero_se_guarda_tal_como_dice_el_documento(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['domicilioNumeroExt' => 'S/N', 'domicilioNumeroInt' => null]));

        $this->assertSame('S/N', $this->fila($escuela)->numero_ext);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function entradasInvalidas(): array
    {
        return [
            'fecha futura' => [['fechaEmision' => '2999-01-01'], 'numeroOficialForm.fechaExpedicion'],
            'fecha con formato inválido' => [['fechaEmision' => '15/03/2026'], 'numeroOficialForm.fechaExpedicion'],
            'autoridad en blanco' => [['autoridadEmisora' => '   '], 'numeroOficialForm.autoridadEmisora'],
            'autoridad demasiado larga' => [['autoridadEmisora' => str_repeat('x', 201)], 'numeroOficialForm.autoridadEmisora'],
            'folio demasiado largo' => [['folioDocumento' => str_repeat('F', 51)], 'numeroOficialForm.folioCertificado'],
            'folio en blanco' => [['folioDocumento' => '  '], 'numeroOficialForm.folioCertificado'],
            'número interior demasiado largo' => [['domicilioNumeroInt' => str_repeat('1', 21)], 'numeroOficialForm.numeroInterior'],
            'clave catastral demasiado larga' => [['claveCatastral' => str_repeat('9', 41)], 'numeroOficialForm.claveCatastral'],
            'código postal inválido' => [['domicilioCodigoPostal' => '7600'], 'numeroOficialForm.codigoPostal'],
        ];
    }

    /** @param  array<string, mixed>  $campos */
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
        $this->assertDatabaseCount('certificados_numero_oficial', 0);
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_un_reemplazo_invalido_conserva_el_certificado_anterior(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos(), 'v1.pdf');
        $ruta = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path');

        try {
            $this->registrar($escuela, $this->datos(['folioDocumento' => 'NUEVO', 'fechaEmision' => '2999-01-01']), 'v2.pdf');
            $this->fail('Se esperaba DatosInvalidos.');
        } catch (DatosInvalidos) {
            // esperado
        }

        $this->assertSame($ruta, DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path'));
        Storage::disk('documentos')->assertExists($ruta);
        $this->assertSame('CNO-2026-0001', $this->fila($escuela)->folio_certificado);
    }

    public function test_reemplazar_sobrescribe_los_metadatos(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos());

        $this->registrar($escuela, $this->datos(['domicilioNumeroInt' => null, 'folioDocumento' => null, 'autoridadEmisora' => null, 'claveCatastral' => null, 'fechaEmision' => null]), 'cert2.pdf');

        $fila = $this->fila($escuela);
        $this->assertSame(1, CertificadoNumeroOficial::count());
        $this->assertNull($fila->numero_int);
        $this->assertNull($fila->folio_certificado);
        $this->assertNull($fila->autoridad_emisora);
        $this->assertNull($fila->clave_catastral);
    }

    public function test_un_fallo_dentro_de_la_transaccion_conserva_archivo_y_fila_previos(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos(), 'v1.pdf');
        $ruta = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path');

        CertificadoNumeroOficial::saving(fn () => throw new \RuntimeException('fallo simulado'));
        try {
            $this->registrar($escuela, $this->datos(['folioDocumento' => 'NUEVO']), 'v2.pdf');
            $this->fail('Se esperaba el fallo simulado.');
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertSame($ruta, DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path'));
        Storage::disk('documentos')->assertExists($ruta);
        $this->assertSame('CNO-2026-0001', $this->fila($escuela)->folio_certificado);
        $this->assertCount(1, Storage::disk('documentos')->allFiles("plantel/{$escuela->plantel_id}"));
    }

    public function test_no_toca_el_certificado_de_otro_plantel(): void
    {
        $propia = $this->crearEscuela();
        $ajena = $this->crearEscuela();
        $this->registrar($ajena, $this->datos(['folioDocumento' => 'AJENO']));

        $this->registrar($propia, $this->datos(['folioDocumento' => 'PROPIO']));

        $this->assertSame('AJENO', $this->fila($ajena)->folio_certificado);
        $this->assertSame('PROPIO', $this->fila($propia)->folio_certificado);
    }
}
