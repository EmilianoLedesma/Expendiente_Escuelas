<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\Aplicabilidad;
use App\Application\Documentos\DocumentosCapturados;
use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\EvaluarAplicabilidadDocumentos;
use App\Application\Documentos\ResultadoAplicabilidad;
use App\Domain\Documentos\ComparadorDomicilios;
use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use App\Models\Escuela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/**
 * Integración de la aplicabilidad condicional con la completitud de Paso 2.2 (spec 2026-10-20 §8):
 * `clavesAplicables` (lo que se muestra y acepta subir) no cambia; `clavesRequeridas` y `clavesPendientes`
 * descuentan solo lo que la capa de aplicación declara `NoRequerido`.
 */
class DocumentosCompletosAplicabilidadTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private const CLAVE = 'certificado_numero_oficial';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documentos');
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

    private function escuela(string $tipoPersona = 'fisica'): Escuela
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->registrarResponsable($escuela, $tipoPersona);

        return $escuela;
    }

    /** Sube todo lo aplicable salvo el certificado. */
    private function subirTodoSalvoElCertificado(Escuela $escuela, string $tipoPersona): void
    {
        foreach (app(DocumentosCompletos::class)->clavesAplicables($tipoPersona) as $clave) {
            if ($clave !== self::CLAVE) {
                $this->subirDocumento($escuela, $clave, $this->datosConsistentes($clave, $tipoPersona));
            }
        }
    }

    public function test_con_la_politica_por_defecto_el_contrato_no_cambia(): void
    {
        $escuela = $this->escuela();
        $completos = app(DocumentosCompletos::class);

        $this->assertSame($completos->clavesAplicables('fisica'), $completos->clavesRequeridas($escuela->id, 'fisica'));
        $this->assertContains(self::CLAVE, $completos->clavesPendientes($escuela->id, 'fisica'));
        $this->assertSame(Aplicabilidad::Obligatorio, $completos->aplicabilidad($escuela->id, 'fisica')[self::CLAVE]->estado);
    }

    #[DataProvider('tiposDePersona')]
    public function test_un_certificado_no_requerido_sale_de_requeridas_y_pendientes_pero_sigue_aplicable(string $tipoPersona): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::NoRequerido);
        $escuela = $this->escuela($tipoPersona);
        $completos = app(DocumentosCompletos::class);

        $this->assertContains(self::CLAVE, $completos->clavesAplicables($tipoPersona), 'se sigue mostrando y aceptando');
        $this->assertNotContains(self::CLAVE, $completos->clavesRequeridas($escuela->id, $tipoPersona));
        $this->assertNotContains(self::CLAVE, $completos->clavesPendientes($escuela->id, $tipoPersona));
    }

    /** @return array<string, array{0: string}> */
    public static function tiposDePersona(): array
    {
        return ['física' => ['fisica'], 'moral' => ['moral'], 'física con gestor' => ['fisica_con_gestor']];
    }

    public function test_sin_el_certificado_pero_no_requerido_el_paso_2_2_queda_completo(): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::NoRequerido);
        $escuela = $this->escuela();
        $this->subirTodoSalvoElCertificado($escuela, 'fisica');

        $this->assertTrue(app(DocumentosCompletos::class)->paraEscuela($escuela->id, 'fisica'));
    }

    public function test_sin_el_certificado_y_obligatorio_el_paso_2_2_sigue_incompleto(): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::Obligatorio);
        $escuela = $this->escuela();
        $this->subirTodoSalvoElCertificado($escuela, 'fisica');

        $completos = app(DocumentosCompletos::class);
        $this->assertFalse($completos->paraEscuela($escuela->id, 'fisica'));
        $this->assertSame([self::CLAVE], $completos->clavesPendientes($escuela->id, 'fisica'));
    }

    public function test_aplicabilidad_pendiente_de_determinar_se_trata_como_exigible(): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::PendienteDeterminar);
        $escuela = $this->escuela();
        $this->subirTodoSalvoElCertificado($escuela, 'fisica');

        $this->assertSame([self::CLAVE], app(DocumentosCompletos::class)->clavesPendientes($escuela->id, 'fisica'));
    }

    public function test_un_certificado_ya_cargado_se_conserva_cuando_deja_de_ser_exigible(): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::Obligatorio);
        $escuela = $this->escuela();
        $this->subirTodosConDatos($escuela);
        $this->assertTrue(app(DocumentosCompletos::class)->paraEscuela($escuela->id, 'fisica'));

        $this->fijarAplicabilidad(Aplicabilidad::NoRequerido);

        $this->assertDatabaseCount('certificados_numero_oficial', 1);
        $this->assertTrue(app(DocumentosCapturados::class)->paraEscuela($escuela->id, 'fisica')->has(self::CLAVE), 'el documento sigue visible y reemplazable');
        $this->assertTrue(app(DocumentosCompletos::class)->paraEscuela($escuela->id, 'fisica'));
    }

    public function test_si_la_condicion_cambia_despues_de_cargar_los_documentos_el_certificado_vuelve_a_ser_exigible(): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::NoRequerido);
        $escuela = $this->escuela();
        $this->subirTodoSalvoElCertificado($escuela, 'fisica');
        $this->assertTrue(app(DocumentosCompletos::class)->paraEscuela($escuela->id, 'fisica'));

        $this->fijarAplicabilidad(Aplicabilidad::PendienteDeterminar);

        $this->assertFalse(app(DocumentosCompletos::class)->paraEscuela($escuela->id, 'fisica'));
    }

    public function test_aplicabilidad_expone_solo_las_claves_condicionales_del_tipo_de_persona(): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::NoRequerido);
        $escuela = $this->escuela();

        $resultado = app(DocumentosCompletos::class)->aplicabilidad($escuela->id, 'fisica');

        $this->assertSame([self::CLAVE], array_keys($resultado));
        $this->assertSame(Aplicabilidad::NoRequerido, $resultado[self::CLAVE]->estado);
    }

    public function test_los_demas_documentos_no_cambian_de_comportamiento(): void
    {
        $this->fijarAplicabilidad(Aplicabilidad::NoRequerido);
        $escuela = $this->escuela();
        $completos = app(DocumentosCompletos::class);

        $esperadas = array_values(array_diff($completos->clavesAplicables('fisica'), [self::CLAVE]));
        $this->assertSame($esperadas, $completos->clavesPendientes($escuela->id, 'fisica'));
    }
}
