<?php

namespace Tests\Feature\Validacion;

use App\Application\Documentos\Aplicabilidad;
use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\EvaluarAplicabilidadDocumentos;
use App\Application\Documentos\ResultadoAplicabilidad;
use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Application\Tramite\EnviarTramite;
use App\Application\Tramite\EstadoPaso2;
use App\Application\Tramite\ResumenTramite;
use App\Application\Validaciones\ConstruirContextoValidacion;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Domain\Documentos\ComparadorDomicilios;
use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use App\Domain\Validaciones\Documental\Reglas\DocumentosRequeridosPresentes;
use App\Models\DocumentoPlantel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use App\Models\NivelEducativo;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\DictamenDeEjemplo;
use Tests\TestCase;

/**
 * Una sola fuente de verdad por decisión (spec docs/specs/2026-10-20-paso2-integracion-end-to-end.md §6.2):
 * checklist (DocumentosCompletos), compuertas (EstadoPaso2), resumen, contexto del motor documental, validación final
 * y envío deben coincidir en qué documentos se requieren; y el envío vuelve a evaluar el estado vigente, nunca una
 * evaluación anterior.
 */
class CoherenciaEntreConsumidoresTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private const CERTIFICADO = 'certificado_numero_oficial';

    private function fijarAplicabilidadDelCertificado(Aplicabilidad $estado): void
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

    /** @param  list<FilaValidacion>  $filas */
    private function fila(array $filas, string $clave): FilaValidacion
    {
        foreach ($filas as $fila) {
            if ($fila->clave === $clave) {
                return $fila;
            }
        }

        $this->fail("No hay fila {$clave}.");
    }

    private function usuario(Escuela $escuela): int
    {
        return (int) $escuela->solicitante->user->id;
    }

    /** @return array<string, array{Aplicabilidad}> */
    public static function aplicabilidades(): array
    {
        return [
            'obligatorio' => [Aplicabilidad::Obligatorio],
            'pendiente de determinar' => [Aplicabilidad::PendienteDeterminar],
            'no requerido' => [Aplicabilidad::NoRequerido],
        ];
    }

    #[DataProvider('aplicabilidades')]
    public function test_checklist_compuerta_resumen_motor_y_validacion_final_coinciden_en_los_requeridos(Aplicabilidad $estado): void
    {
        $this->fijarAplicabilidadDelCertificado($estado);
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        // Sin certificado: solo cuenta como faltante si la aplicabilidad lo exige.
        DocumentoPlantel::where('plantel_id', $escuela->plantel_id)->where('tipo_documento_id', TipoDocumento::where('clave', self::CERTIFICADO)->value('id'))->delete();

        $requeridas = app(DocumentosCompletos::class)->clavesRequeridas($escuela->id, 'fisica');
        $exigible = $estado->exigeCarga();

        $this->assertSame($exigible, in_array(self::CERTIFICADO, $requeridas, true));
        $this->assertSame($requeridas, app(ConstruirContextoValidacion::class)->ejecutar($escuela->id)->clavesRequeridas, 'el motor documental usa la misma lista que el checklist');
        $this->assertSame(! $exigible, app(EstadoPaso2::class)->generalesSatisfechos($escuela->id), 'la compuerta coincide con el checklist');

        $resumen = app(ResumenTramite::class)->paraEscuela($escuela->id);
        $documentos = collect($resumen->generales)->firstWhere('clave', 'documentos');
        $this->assertSame($exigible ? 'en_curso' : 'completado', $documentos->estado, 'el resumen coincide con el checklist');
        $this->assertSame(! $exigible, $resumen->completo);

        if ($exigible) {
            // La validación final ni siquiera corre: el resumen (misma fuente) dice que falta una sección.
            $this->expectException(PrecondicionIncumplida::class);
        }

        $validacion = app(EjecutarValidacionFinal::class)->evaluar($escuela->id);
        $this->assertSame('cumple', $this->fila($validacion->filas, DocumentosRequeridosPresentes::CLAVE)->estado);
    }

    public function test_agregar_un_nivel_despues_de_validar_impide_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $primaria = $this->completarTramite($escuela);
        $this->assertTrue(app(EjecutarValidacionFinal::class)->ejecutar($escuela->id)->listaParaEnvio);

        app(RegistrarNivelesSeleccionados::class)->ejecutar($escuela->id, agregar: [(int) NivelEducativo::where('clave', 'secundaria')->value('id')]);

        try {
            app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));
            $this->fail('El nivel nuevo no está capturado: no se debía enviar.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(EjecutarValidacionFinal::ETAPA, $e->etapaFaltante);
        }

        $this->assertSame(0, DB::table('historial_estados_expediente')->count());
        $this->assertSame('en_captura', DB::table('estados_expediente')->where('id', $primaria->fresh()->estado_id)->value('clave'));
        $this->assertSame(1, EvaluacionValidacion::count());
    }

    public function test_un_documento_que_vence_despues_de_validar_impide_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, reemplazos: [
            'dictamen_uso_suelo' => DictamenDeEjemplo::conVigencia('fecha_expresa', now()->addDay()->toDateString()),
        ]);
        $this->assertTrue(app(EjecutarValidacionFinal::class)->ejecutar($escuela->id)->listaParaEnvio);

        Carbon::setTestNow(now()->addDays(3));

        try {
            app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));
            $this->fail('El dictamen venció: no se debía enviar.');
        } catch (PrecondicionIncumplida $e) {
            $this->assertSame(EjecutarValidacionFinal::ETAPA, $e->etapaFaltante);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(0, DB::table('historial_estados_expediente')->count());
    }

    public function test_un_cambio_de_tipo_de_persona_despues_de_validar_impide_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);
        $this->assertTrue(app(EjecutarValidacionFinal::class)->ejecutar($escuela->id)->listaParaEnvio);

        // Moral pide documentos propios (acta constitutiva, poder del representante) que no se han subido.
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'moral', razonSocial: 'Colegio Ejemplo A.C.', nombreRepresentanteLegal: 'Juan Pérez Gómez'));

        $this->assertSame(EstadoPaso2::DOCUMENTOS, app(EstadoPaso2::class)->etapaFaltante($escuela->id));
        $this->expectException(PrecondicionIncumplida::class);
        app(EnviarTramite::class)->ejecutar($escuela->id, $this->usuario($escuela));
    }

    public function test_quitar_un_nivel_no_borra_los_documentos_generales_ni_reinicia_los_demas_niveles(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $primaria = $this->completarTramite($escuela);
        app(RegistrarNivelesSeleccionados::class)->ejecutar($escuela->id, agregar: [(int) NivelEducativo::where('clave', 'secundaria')->value('id')]);
        $documentos = DocumentoPlantel::count() + DB::table('documentos_escuela')->count();
        $pasos = DB::table('escuela_nivel_pasos')->where('escuela_nivel_id', $primaria->id)->where('estado', 'completado')->count();

        app(RegistrarNivelesSeleccionados::class)->ejecutar($escuela->id, agregar: [], quitar: [(int) NivelEducativo::where('clave', 'secundaria')->value('id')]);

        $this->assertSame($documentos, DocumentoPlantel::count() + DB::table('documentos_escuela')->count());
        $this->assertSame($pasos, DB::table('escuela_nivel_pasos')->where('escuela_nivel_id', $primaria->id)->where('estado', 'completado')->count());
        $this->assertSame(1, EscuelaNivel::where('escuela_id', $escuela->id)->count());
        $this->assertTrue(app(ResumenTramite::class)->paraEscuela($escuela->id)->completo);
    }
}
