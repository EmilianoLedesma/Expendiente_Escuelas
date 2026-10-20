<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\Excepciones\DatosInvalidos;
use App\Models\ConstanciaSeguridadEstructural;
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
use Tests\TestCase;

/**
 * Constancia de Seguridad Estructural: el caso de uso es la autoridad de las invariantes
 * (docs/superpowers/specs/2026-10-20-constancia-seguridad-design.md §5), con o sin Livewire.
 */
class RegistrarConstanciaSeguridadTest extends TestCase
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
     * Captura completa válida; $campos pisa lo que cada prueba quiera cambiar.
     *
     * @param  array<string, mixed>  $campos
     */
    private function datos(array $campos = []): DatosDocumento
    {
        return new DatosDocumento(...[
            'fechaEmision' => '2026-05-10',
            'folioConstancia' => 'CSE-2026-001',
            'tipoVigencia' => 'fecha_expresa',
            'vigenciaConstancia' => '2031-05-10',
            'peritoNombre' => 'Ing. Juan Pérez',
            'calidadProfesional' => 'corresponsable_seguridad_estructural',
            'peritoCedulaProfesional' => '1234567',
            'peritoRegistroDro' => 'CSE-100',
            'peritoRegistroAutoridad' => 'Municipio de Querétaro',
            'peritoRegistroFecha' => '2026-02-10',
            'peritoRegistroVencimiento' => '2027-02-09',
            ...$campos,
        ]);
    }

    private function registrar(Escuela $escuela, DatosDocumento $datos, string $nombre = 'c.pdf'): void
    {
        (new RegistrarDocumento)->ejecutar($escuela->id, 'constancia_seguridad_estructural', UploadedFile::fake()->create($nombre, 10, 'application/pdf'), $datos);
    }

    private function extension(Escuela $escuela): ConstanciaSeguridadEstructural
    {
        return ConstanciaSeguridadEstructural::query()
            ->whereIn('documento_plantel_id', DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->select('id'))
            ->firstOrFail();
    }

    public function test_persiste_la_captura_completa(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos());

        $fila = $this->extension($escuela);
        $this->assertSame('corresponsable_seguridad_estructural', $fila->calidad_profesional);
        $this->assertNull($fila->calidad_profesional_otra);
        $this->assertSame('CSE-2026-001', $fila->folio_constancia);
        $this->assertSame('fecha_expresa', $fila->tipo_vigencia);
        $this->assertSame('2031-05-10', $fila->vigencia_constancia);
        $this->assertSame('CSE-100', $fila->perito_registro_dro);
        $this->assertSame('2026-02-10', $fila->perito_registro_fecha);
        $this->assertSame('2027-02-09', $fila->perito_registro_vencimiento);
        $this->assertDatabaseHas('documentos_plantel', ['plantel_id' => $escuela->plantel_id, 'fecha_emision' => '2026-05-10', 'fecha_vigencia' => '2031-05-10']);
    }

    public function test_sin_fecha_expresa_no_inventa_vencimiento_aunque_el_llamador_mande_una(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['tipoVigencia' => 'sin_fecha_expresa', 'vigenciaConstancia' => '2031-05-10']));

        $this->assertSame('sin_fecha_expresa', $this->extension($escuela)->tipo_vigencia);
        $this->assertNull($this->extension($escuela)->vigencia_constancia);
        $this->assertDatabaseHas('documentos_plantel', ['plantel_id' => $escuela->plantel_id, 'fecha_vigencia' => null]);
    }

    public function test_calidad_otra_guarda_su_descripcion_y_cualquier_otra_la_anula(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['calidadProfesional' => 'otra', 'calidadProfesionalOtra' => 'Perito estructurista externo']));
        $this->assertSame('Perito estructurista externo', $this->extension($escuela)->calidad_profesional_otra);

        $this->registrar($escuela, $this->datos(['calidadProfesional' => 'dro', 'calidadProfesionalOtra' => 'Perito estructurista externo']), 'c2.pdf');
        $this->assertSame('dro', $this->extension($escuela)->calidad_profesional);
        $this->assertNull($this->extension($escuela)->calidad_profesional_otra);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function entradasInvalidas(): array
    {
        return [
            'emisión futura' => [['fechaEmision' => '2999-01-01', 'vigenciaConstancia' => '2999-06-01'], 'constanciaForm.fechaEmision'],
            'emisión ausente' => [['fechaEmision' => null], 'constanciaForm.fechaEmision'],
            'emisión con formato inválido' => [['fechaEmision' => '10/05/2026'], 'constanciaForm.fechaEmision'],
            'vencimiento anterior a la emisión' => [['vigenciaConstancia' => '2026-05-09'], 'constanciaForm.vigenciaConstancia'],
            'fecha expresa sin fecha' => [['vigenciaConstancia' => null], 'constanciaForm.vigenciaConstancia'],
            'tipo de vigencia desconocido' => [['tipoVigencia' => 'cinco_anios'], 'constanciaForm.tipoVigencia'],
            'calidad ausente en captura completa' => [['calidadProfesional' => null], 'constanciaForm.calidadProfesional'],
            'calidad desconocida' => [['calidadProfesional' => 'perito'], 'constanciaForm.calidadProfesional'],
            'otra sin descripción' => [['calidadProfesional' => 'otra', 'calidadProfesionalOtra' => null], 'constanciaForm.calidadProfesionalOtra'],
            'otra con descripción en blanco' => [['calidadProfesional' => 'otra', 'calidadProfesionalOtra' => '   '], 'constanciaForm.calidadProfesionalOtra'],
            'nombre ausente' => [['peritoNombre' => null], 'constanciaForm.peritoNombre'],
            'nombre demasiado largo' => [['peritoNombre' => 'x'.str_repeat('y', 200)], 'constanciaForm.peritoNombre'],
            'registro ausente' => [['peritoRegistroDro' => null], 'constanciaForm.peritoRegistroDro'],
            'folio demasiado largo' => [['folioConstancia' => str_repeat('F', 51)], 'constanciaForm.folioConstancia'],
            'fecha de registro futura' => [['peritoRegistroFecha' => '2999-01-01'], 'constanciaForm.peritoRegistroFecha'],
            'vencimiento del registro anterior a su fecha' => [['peritoRegistroFecha' => '2026-02-10', 'peritoRegistroVencimiento' => '2026-02-09'], 'constanciaForm.peritoRegistroVencimiento'],
            'vencimiento del registro con formato inválido' => [['peritoRegistroVencimiento' => 'mañana'], 'constanciaForm.peritoRegistroVencimiento'],
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
        $this->assertDatabaseCount('constancias_seguridad_estructural', 0);
        $this->assertSame([], Storage::disk('documentos')->allFiles());
    }

    public function test_el_vencimiento_el_mismo_dia_de_la_emision_es_valido(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, $this->datos(['vigenciaConstancia' => '2026-05-10']));

        $this->assertSame('2026-05-10', $this->extension($escuela)->vigencia_constancia);
    }

    public function test_un_llamador_anterior_sin_tipo_de_vigencia_sigue_funcionando_e_infiere_el_tipo(): void
    {
        $con = $this->crearEscuela();
        $sin = $this->crearEscuela();

        $this->registrar($con, new DatosDocumento(fechaEmision: '2026-05-10', peritoNombre: 'Ing. Juan Pérez', peritoRegistroDro: 'DRO-100', vigenciaConstancia: '2027-05-10'));
        $this->registrar($sin, new DatosDocumento(fechaEmision: '2026-05-10', peritoNombre: 'Ing. Juan Pérez'));

        $this->assertSame('fecha_expresa', $this->extension($con)->tipo_vigencia);
        $this->assertSame('2027-05-10', $this->extension($con)->vigencia_constancia);
        $this->assertSame('no_capturada', $this->extension($sin)->tipo_vigencia);
        $this->assertNull($this->extension($sin)->calidad_profesional);
    }

    public function test_un_llamador_anterior_conserva_el_dato_historico_del_registro(): void
    {
        $escuela = $this->crearEscuela();

        $this->registrar($escuela, new DatosDocumento(fechaEmision: '2026-05-10', peritoNombre: 'Ing. Juan Pérez', peritoRegistroVigencia: '2025-01-01'));

        $this->assertSame('2025-01-01', $this->extension($escuela)->perito_registro_vigencia);
    }

    public function test_reemplazar_sobrescribe_toda_la_extension_y_borra_lo_que_no_se_manda(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos(['peritoRegistroVigencia' => '2025-01-01']));

        $this->registrar($escuela, $this->datos([
            'folioConstancia' => null, 'peritoCedulaProfesional' => null, 'peritoRegistroFecha' => null,
            'peritoRegistroVencimiento' => null, 'tipoVigencia' => 'sin_fecha_expresa', 'vigenciaConstancia' => null,
        ]), 'c2.pdf');

        $fila = $this->extension($escuela);
        $this->assertSame(1, ConstanciaSeguridadEstructural::count());
        $this->assertNull($fila->folio_constancia);
        $this->assertNull($fila->perito_registro_fecha);
        $this->assertNull($fila->perito_registro_vencimiento);
        $this->assertNull($fila->perito_registro_vigencia, 'ningún dato del PDF reemplazado sobrevive');
        $this->assertSame('sin_fecha_expresa', $fila->tipo_vigencia);
        $this->assertCount(1, Storage::disk('documentos')->allFiles("plantel/{$escuela->plantel_id}"));
    }

    public function test_un_fallo_dentro_de_la_transaccion_conserva_archivo_y_extension_previos(): void
    {
        $escuela = $this->crearEscuela();
        $this->registrar($escuela, $this->datos(), 'v1.pdf');
        $rutaAnterior = DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path');

        ConstanciaSeguridadEstructural::saving(fn () => throw new \RuntimeException('fallo simulado'));
        try {
            $this->registrar($escuela, $this->datos(['folioConstancia' => 'NUEVO']), 'v2.pdf');
            $this->fail('Se esperaba el fallo simulado.');
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertSame($rutaAnterior, DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->value('archivo_path'));
        Storage::disk('documentos')->assertExists($rutaAnterior);
        $this->assertSame('CSE-2026-001', $this->extension($escuela)->folio_constancia);
        $this->assertCount(1, Storage::disk('documentos')->allFiles("plantel/{$escuela->plantel_id}"), 'no queda archivo nuevo huérfano');
    }

    public function test_no_toca_la_constancia_de_otro_plantel(): void
    {
        $propia = $this->crearEscuela();
        $ajena = $this->crearEscuela();
        $this->registrar($ajena, $this->datos(['folioConstancia' => 'AJENA']));

        $this->registrar($propia, $this->datos(['folioConstancia' => 'PROPIA']));

        $this->assertSame('AJENA', $this->extension($ajena)->folio_constancia);
        $this->assertSame('PROPIA', $this->extension($propia)->folio_constancia);
    }
}
