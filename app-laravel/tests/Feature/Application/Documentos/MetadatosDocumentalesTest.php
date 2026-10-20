<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\MetadatosDocumentales;
use App\Application\Documentos\RegistrarDocumento;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\DictamenDeEjemplo;
use Tests\TestCase;

/** Lectura de metadatos documentales para SEDEQ (spec 2026-10-20 §7.2): fiel, sin inventar, sin escribir. */
class MetadatosDocumentalesTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($this->escuela);
    }

    /** @return array<string, list<array{etiqueta: string, valor: string, estado: string|null}>> */
    private function leer(): array
    {
        return app(MetadatosDocumentales::class)->paraEscuela($this->escuela->id, app(DocumentosCompletos::class)->clavesAplicables('fisica'));
    }

    /** @param  list<array{etiqueta: string, valor: string, estado: string|null}>  $filas */
    private function valor(array $filas, string $etiqueta): ?array
    {
        foreach ($filas as $fila) {
            if ($fila['etiqueta'] === $etiqueta) {
                return $fila;
            }
        }

        return null;
    }

    private function subir(string $clave, DatosDocumento $datos): void
    {
        app(RegistrarDocumento::class)->ejecutar($this->escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), $datos);
    }

    public function test_los_datos_de_identidad_y_del_dictamen_se_leen_tal_como_se_capturaron(): void
    {
        $this->subir('ine', $this->datosConsistentes('ine'));
        $this->subir('dictamen_uso_suelo', DictamenDeEjemplo::conVigencia('fecha_expresa', now()->addYear()->toDateString()));

        $metadatos = $this->leer();

        $this->assertSame('PEREZ GOMEZ JUAN', $this->valor($metadatos['ine'], 'Nombre')['valor']);
        $this->assertSame(self::CURP_TITULAR, $this->valor($metadatos['ine'], 'CURP')['valor']);
        $this->assertSame('DUS-2026-001', $this->valor($metadatos['dictamen_uso_suelo'], 'Folio')['valor']);
        $this->assertSame('vigente', $this->valor($metadatos['dictamen_uso_suelo'], 'Vigencia declarada')['estado']);
    }

    public function test_un_dictamen_con_fecha_expresa_pasada_se_marca_vencido_y_uno_sin_fecha_expresa_no(): void
    {
        $this->subir('dictamen_uso_suelo', DictamenDeEjemplo::vencido());
        $this->assertSame('vencido', $this->valor($this->leer()['dictamen_uso_suelo'], 'Vigencia declarada')['estado']);

        $this->subir('dictamen_uso_suelo', DictamenDeEjemplo::conVigencia('sin_fecha_expresa'));
        $this->assertSame('sin_fecha_expresa', $this->valor($this->leer()['dictamen_uso_suelo'], 'Vigencia declarada')['estado']);
    }

    /** Registro histórico: Protección Civil migrado a `no_capturada`, sin autoridad ni riesgo. */
    public function test_un_registro_historico_dice_no_capturado_y_no_determinada_sin_inventar_incumplimientos(): void
    {
        $this->subir('visto_bueno_proteccion_civil', new DatosDocumento(fechaEmision: now()->toDateString()));
        DB::table('vistos_buenos_proteccion_civil')->update(['tipo_instrumento' => null, 'autoridad_emisora' => null, 'clasificacion_riesgo' => null, 'ejercicio' => null, 'tipo_vigencia' => 'no_capturada']);

        $pc = $this->leer()['visto_bueno_proteccion_civil'];

        $this->assertSame('No capturado', $this->valor($pc, 'Autoridad emisora')['valor']);
        $this->assertSame('No capturado', $this->valor($pc, 'Clasificación de riesgo')['valor']);
        $this->assertSame('no_determinada', $this->valor($pc, 'Vigencia declarada')['estado']);
        $this->assertNotContains('vencido', array_column($pc, 'estado'));
    }

    public function test_un_documento_sin_su_fila_de_datos_lo_dice(): void
    {
        DocumentoPlantel::create(['plantel_id' => $this->escuela->plantel_id, 'tipo_documento_id' => TipoDocumento::where('clave', 'dictamen_uso_suelo')->value('id'), 'archivo_path' => 'x.pdf']);

        $this->assertSame('No capturado', $this->valor($this->leer()['dictamen_uso_suelo'], 'Datos del documento')['valor']);
    }

    public function test_la_acreditacion_muestra_solo_los_campos_de_su_figura_y_la_vigencia_del_contrato(): void
    {
        $this->subir('escritura_inmueble', new DatosDocumento(
            tipoAcreditacion: 'arrendamiento', arrendadorComodante: 'Inmobiliaria Sur SA', arrendatarioComodatario: 'Colegio Norte SA de CV',
            fechaContrato: '2025-01-10', fechaInicioVigencia: '2025-02-01', vigenciaContrato: now()->addYears(2)->toDateString(), usoAutorizado: 'Prestación de servicio educativo',
        ));

        $acreditacion = $this->leer()['escritura_inmueble'];

        $this->assertSame('Arrendamiento', $this->valor($acreditacion, 'Figura')['valor']);
        $this->assertSame('Inmobiliaria Sur SA', $this->valor($acreditacion, 'Arrendador o comodante')['valor']);
        $this->assertNull($this->valor($acreditacion, 'Número de escritura'), 'un campo de otra figura no aplica: no se muestra');
        $this->assertSame('vigente', $this->valor($acreditacion, 'Vigencia del contrato')['estado']);
    }

    public function test_el_certificado_muestra_la_entidad_y_el_municipio_del_catalogo(): void
    {
        $entidad = DB::table('entidades_federativas')->insertGetId(['clave' => 22, 'nombre' => 'QUERÉTARO']);
        $municipio = DB::table('municipios')->insertGetId(['entidad_federativa_id' => $entidad, 'clave' => 14, 'nombre' => 'QUERÉTARO']);
        $this->subir('certificado_numero_oficial', new DatosDocumento(
            domicilioCalle: 'Av. Juárez', domicilioNumeroExt: '12', domicilioColonia: 'Centro', domicilioCodigoPostal: '76000',
            domicilioEntidadFederativaId: $entidad, domicilioMunicipioId: $municipio,
        ));

        $certificado = $this->leer()['certificado_numero_oficial'];

        $this->assertSame('QUERÉTARO', $this->valor($certificado, 'Municipio (catálogo)')['valor']);
        $this->assertSame('No capturado', $this->valor($certificado, 'Folio')['valor']);
    }

    public function test_leer_no_escribe_nada(): void
    {
        $this->subirTodosConDatos($this->escuela);
        $escrituras = 0;
        DB::listen(function ($consulta) use (&$escrituras) {
            if (preg_match('/^\s*(insert|update|delete)/i', $consulta->sql) === 1) {
                $escrituras++;
            }
        });

        $this->leer();

        $this->assertSame(0, $escrituras);
    }
}
