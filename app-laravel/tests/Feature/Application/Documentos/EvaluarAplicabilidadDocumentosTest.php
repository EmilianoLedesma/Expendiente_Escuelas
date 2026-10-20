<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\Aplicabilidad;
use App\Application\Documentos\DTO\FuenteDomicilio;
use App\Application\Documentos\EvaluarAplicabilidadDocumentos;
use App\Application\Documentos\FuentesDomicilioMunicipal;
use App\Domain\Documentos\ComparadorDomicilios;
use App\Domain\Documentos\Domicilio;
use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Aplicabilidad del Certificado de Número Oficial (spec 2026-10-20 §5). La condición «solo cuando hay
 * domicilios diferentes» es Documentado, no contrastado y su alcance por nivel es Pendiente de SEDEQ:
 * sin alcance configurado el certificado es obligatorio, y la ausencia de evidencia nunca lo vuelve opcional.
 */
class EvaluarAplicabilidadDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'certificado_numero_oficial';

    protected function setUp(): void
    {
        parent::setUp();
        (new CatalogoMinimoSeeder)->run();
    }

    private function escuela(string ...$niveles): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Avenida Hidalgo', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        ResponsableLegal::create(['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
        foreach ($niveles as $nivel) {
            EscuelaNivel::create([
                'escuela_id' => $escuela->id,
                'nivel_educativo_id' => NivelEducativo::where('clave', $nivel)->value('id'),
                'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
                'tipo_tramite' => 'alta_nueva',
            ]);
        }

        return $escuela;
    }

    /**
     * @param  list<Domicilio|FuenteDomicilio>  $evidencia  domicilios de documentos municipales (hoy ningún documento
     *                                                      capturado los guarda, ver FuentesDomicilioMunicipal); cada Domicilio es un documento distinto.
     */
    private function evaluador(array $evidencia = []): EvaluarAplicabilidadDocumentos
    {
        $fuentes = array_map(fn (Domicilio|FuenteDomicilio $d, int $i) => $d instanceof FuenteDomicilio ? $d : self::fuente($i + 1, $d), $evidencia, array_keys($evidencia));

        return new EvaluarAplicabilidadDocumentos(new ComparadorDomicilios(new NormalizadorDomicilio), new class($fuentes) extends FuentesDomicilioMunicipal
        {
            /** @param  list<FuenteDomicilio>  $fuentes */
            public function __construct(private array $fuentes) {}

            public function para(int $plantelId): array
            {
                return $this->fuentes;
            }
        });
    }

    private static function fuente(int $documentoId, Domicilio $domicilio, string $tipo = 'dictamen_uso_suelo'): FuenteDomicilio
    {
        return new FuenteDomicilio($documentoId, $tipo, $domicilio, 'Municipio de Querétaro', 'municipio_queretaro', 'captura_estructurada', 'Declarado por el solicitante');
    }

    /** @param  list<string>  $alcance */
    private function alcance(array $alcance): void
    {
        config(['documentos.numero_oficial.alcance_condicional' => $alcance]);
    }

    private function coincidente(): Domicilio
    {
        return new Domicilio('Av. Hidalgo', '12', null, 'Col. Centro', 'Querétaro', '76000');
    }

    private function distinto(): Domicilio
    {
        return new Domicilio('Avenida Zaragoza', '5', null, 'Centro', 'Querétaro', '76000');
    }

    public function test_sin_alcance_confirmado_el_certificado_es_obligatorio_y_el_comportamiento_no_cambia(): void
    {
        $escuela = $this->escuela('primaria');

        $resultado = $this->evaluador([$this->coincidente(), $this->coincidente()])->para($escuela->id, self::CLAVE);

        $this->assertSame(Aplicabilidad::Obligatorio, $resultado->estado);
        $this->assertStringContainsString('SEDEQ', $resultado->motivo);
        $this->assertSame('Pendiente de SEDEQ', $resultado->certeza);
    }

    public function test_la_politica_por_defecto_es_la_vacia(): void
    {
        $this->assertSame([], config('documentos.numero_oficial.alcance_condicional'));
    }

    public function test_con_alcance_pero_sin_niveles_elegidos_la_aplicabilidad_queda_pendiente(): void
    {
        $this->alcance(['preescolar', 'primaria', 'secundaria']);

        $this->assertSame(Aplicabilidad::PendienteDeterminar, $this->evaluador()->para($this->escuela()->id, self::CLAVE)->estado);
    }

    public function test_un_nivel_fuera_del_alcance_mantiene_la_obligatoriedad(): void
    {
        $this->alcance(['preescolar', 'primaria', 'secundaria']);
        $escuela = $this->escuela('primaria', 'media_superior');

        $this->assertSame(Aplicabilidad::Obligatorio, $this->evaluador([$this->coincidente(), $this->coincidente()])->para($escuela->id, self::CLAVE)->estado);
    }

    public function test_dentro_del_alcance_y_sin_evidencia_la_aplicabilidad_queda_pendiente(): void
    {
        $this->alcance(['primaria']);
        $escuela = $this->escuela('primaria');

        $resultado = $this->evaluador()->para($escuela->id, self::CLAVE);

        $this->assertSame(Aplicabilidad::PendienteDeterminar, $resultado->estado);
        $this->assertTrue($resultado->estado->exigeCarga(), 'la ausencia de evidencia no equivale a ausencia de discrepancia');
    }

    public function test_dentro_del_alcance_con_evidencia_suficiente_de_coincidencia_no_se_requiere(): void
    {
        $this->alcance(['primaria', 'secundaria']);
        $escuela = $this->escuela('primaria', 'secundaria');

        $resultado = $this->evaluador([$this->coincidente(), $this->coincidente()])->para($escuela->id, self::CLAVE);

        $this->assertSame(Aplicabilidad::NoRequerido, $resultado->estado);
        $this->assertFalse($resultado->estado->exigeCarga());
    }

    public function test_una_sola_fuente_coincidente_no_basta(): void
    {
        $this->alcance(['primaria']);
        $escuela = $this->escuela('primaria');

        $this->assertSame(Aplicabilidad::PendienteDeterminar, $this->evaluador([$this->coincidente()])->para($escuela->id, self::CLAVE)->estado);
    }

    public function test_si_alguna_fuente_difiere_no_se_concluye_y_sigue_siendo_exigible(): void
    {
        $this->alcance(['primaria']);
        $escuela = $this->escuela('primaria');

        $resultado = $this->evaluador([$this->coincidente(), $this->coincidente(), $this->distinto()])->para($escuela->id, self::CLAVE);

        $this->assertSame(Aplicabilidad::PendienteDeterminar, $resultado->estado);
        $this->assertTrue($resultado->estado->exigeCarga());
    }

    public function test_el_resultado_es_reproducible_y_trae_motivo_fundamento_y_certeza(): void
    {
        $this->alcance(['primaria']);
        $escuela = $this->escuela('primaria');
        $evaluador = $this->evaluador([$this->coincidente(), $this->coincidente()]);

        $primero = $evaluador->para($escuela->id, self::CLAVE);
        $segundo = $evaluador->para($escuela->id, self::CLAVE);

        $this->assertEquals($primero, $segundo);
        $this->assertNotSame('', $primero->motivo);
        $this->assertNotSame('', $primero->fundamento);
        $this->assertNotSame('', $primero->certeza);
    }

    public function test_los_demas_documentos_siguen_siendo_obligatorios(): void
    {
        $this->alcance(['primaria']);
        $escuela = $this->escuela('primaria');

        foreach (['ine', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'visto_bueno_proteccion_civil', 'plano_inmueble'] as $clave) {
            $this->assertSame(Aplicabilidad::Obligatorio, $this->evaluador([$this->coincidente(), $this->coincidente()])->para($escuela->id, $clave)->estado, $clave);
        }
    }

    public function test_solo_el_certificado_aparece_entre_los_documentos_condicionales(): void
    {
        $this->assertSame([self::CLAVE], array_keys($this->evaluador()->condicionales($this->escuela('primaria')->id)));
    }

    /** Una misma fuente (mismo documento) no se cuenta dos veces. */
    public function test_el_mismo_documento_no_cuenta_dos_veces(): void
    {
        $this->alcance(['primaria']);
        $escuela = $this->escuela('primaria');

        $resultado = $this->evaluador([self::fuente(7, $this->coincidente()), self::fuente(7, $this->coincidente())])->para($escuela->id, self::CLAVE);

        $this->assertSame(Aplicabilidad::PendienteDeterminar, $resultado->estado);
    }

    /** El certificado no puede demostrar que él mismo no se necesita. */
    public function test_el_propio_certificado_no_es_evidencia_de_su_exencion(): void
    {
        $this->alcance(['primaria']);
        $escuela = $this->escuela('primaria');

        $resultado = $this->evaluador([
            self::fuente(1, $this->coincidente()),
            self::fuente(2, $this->coincidente(), self::CLAVE),
        ])->para($escuela->id, self::CLAVE);

        $this->assertSame(Aplicabilidad::PendienteDeterminar, $resultado->estado);
    }

    public function test_el_resultado_conserva_los_documentos_usados_como_evidencia(): void
    {
        $this->alcance(['primaria']);
        $escuela = $this->escuela('primaria');

        $resultado = $this->evaluador([self::fuente(3, $this->coincidente()), self::fuente(4, $this->coincidente())])->para($escuela->id, self::CLAVE);

        $this->assertSame(Aplicabilidad::NoRequerido, $resultado->estado);
        $this->assertSame([3, 4], $resultado->evidencia);
    }
}
