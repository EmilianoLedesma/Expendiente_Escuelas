<?php

namespace Tests\Feature\Application\Preregistro;

use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
use App\Application\Preregistro\PlantelNoDisponible;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class IniciarTramiteNuevoTest extends TestCase
{
    use RefreshDatabase;

    private function datosNuevoValidos(): DatosPreregistro
    {
        return new DatosPreregistro(
            bifurcacion: 'nuevo',
            calle: 'Av. Reforma 100',
            colonia: 'Centro',
            municipio: 'Querétaro',
            codigoPostal: '76000',
        );
    }

    /** Plantel ya registrado por el solicitante (escuela sin responsable legal). */
    private function plantelConEscuelaDe(Solicitante $solicitante): Plantel
    {
        $plantel = Plantel::create([
            'calle' => 'Calle Ya Registrada 5',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);
        Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        return $plantel;
    }

    public function test_crea_plantel_y_escuela_para_bifurcacion_nuevo(): void
    {
        $solicitante = Solicitante::factory()->create();

        $resultado = (new IniciarTramiteNuevo)->ejecutar($this->datosNuevoValidos(), $solicitante->id);

        $this->assertDatabaseHas('planteles', [
            'id' => $resultado->plantelId,
            'calle' => 'Av. Reforma 100',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);
        $this->assertDatabaseHas('escuelas', [
            'id' => $resultado->escuelaId,
            'plantel_id' => $resultado->plantelId,
            'solicitante_id' => $solicitante->id,
        ]);
    }

    public function test_reutiliza_plantel_existente_para_bifurcacion_existente(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = $this->plantelConEscuelaDe($solicitante);

        $resultado = (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'existente',
            plantelId: $plantel->id,
        ), $solicitante->id);

        $this->assertDatabaseHas('escuelas', [
            'id' => $resultado->escuelaId,
            'plantel_id' => $plantel->id,
            'solicitante_id' => $solicitante->id,
        ]);
        $this->assertDatabaseCount('planteles', 1);
    }

    public function test_reutiliza_escuela_existente_del_mismo_solicitante_y_plantel(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = $this->plantelConEscuelaDe($solicitante);

        $primero = (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'existente',
            plantelId: $plantel->id,
        ), $solicitante->id);

        $segundo = (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'existente',
            plantelId: $plantel->id,
        ), $solicitante->id);

        $this->assertSame($primero->escuelaId, $segundo->escuelaId);
        $this->assertDatabaseCount('escuelas', 1);
    }

    public function test_crea_una_segunda_escuela_distinta_si_la_primera_ya_tiene_responsable_legal(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = $this->plantelConEscuelaDe($solicitante);

        $primero = (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'existente',
            plantelId: $plantel->id,
        ), $solicitante->id);

        (new RegistrarResponsableLegal)->ejecutar($primero->escuelaId, new DatosResponsableLegal(
            tipoPersona: 'fisica',
            nombre: 'Juana Pérez',
        ));

        $segundo = (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'existente',
            plantelId: $plantel->id,
        ), $solicitante->id);

        $this->assertNotSame($primero->escuelaId, $segundo->escuelaId);
        $this->assertDatabaseCount('escuelas', 2);
    }

    /**
     * Cambio intencional de comportamiento (auditoría 2026-09-23, WS-1.1):
     * antes dos solicitantes distintos podían colgar escuelas del mismo
     * plantel; eso permitía a B leer/sobrescribir documentos_plantel de A.
     * Ahora "existente" exige que el plantel ya tenga una escuela del propio
     * solicitante. Ver docs/decisions/PENDIENTE-plantel-solicitante-cardinalidad.md.
     */
    public function test_rechaza_que_otro_solicitante_reutilice_un_plantel_ajeno(): void
    {
        $solicitanteA = Solicitante::factory()->create();
        $solicitanteB = Solicitante::factory()->create();
        $resultadoA = (new IniciarTramiteNuevo)->ejecutar($this->datosNuevoValidos(), $solicitanteA->id);

        try {
            (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
                bifurcacion: 'existente',
                plantelId: $resultadoA->plantelId,
            ), $solicitanteB->id);
            $this->fail('Se esperaba PlantelNoDisponible.');
        } catch (PlantelNoDisponible) {
        }

        $this->assertDatabaseCount('escuelas', 1);
        $this->assertDatabaseMissing('escuelas', ['solicitante_id' => $solicitanteB->id]);
    }

    public function test_rechaza_plantel_sin_escuelas_del_solicitante(): void
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create([
            'calle' => 'Calle Huérfana 1',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);

        $this->expectException(PlantelNoDisponible::class);

        (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'existente',
            plantelId: $plantel->id,
        ), $solicitante->id);
    }

    public function test_rechaza_dto_de_bifurcacion_nuevo_sin_campos_requeridos(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'nuevo',
            calle: 'Av. Reforma 100',
            // falta colonia, municipio, codigoPostal
        ), $solicitante->id);
    }

    public function test_rechaza_dto_de_bifurcacion_existente_sin_plantel_id(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(bifurcacion: 'existente'), $solicitante->id);
    }

    /**
     * El plantel se escribe primero; forzamos el fallo de la segunda
     * escritura (escuelas) con un listener de modelo temporal para probar
     * que ambas escrituras están dentro de la misma transacción — sin este
     * listener no hay forma de romper la escritura de escuelas desde fuera,
     * ya que sus únicos campos controlados por el caso de uso (plantel_id,
     * solicitante_id) siempre serán válidos en este flujo.
     */
    public function test_no_deja_fila_huerfana_de_plantel_si_falla_la_escritura_de_escuela(): void
    {
        $solicitante = Solicitante::factory()->create();

        Escuela::creating(function () {
            throw new RuntimeException('fallo forzado para la prueba');
        });

        try {
            (new IniciarTramiteNuevo)->ejecutar($this->datosNuevoValidos(), $solicitante->id);
            $this->fail('Se esperaba una excepción.');
        } catch (Throwable $e) {
            $this->assertInstanceOf(RuntimeException::class, $e);
        }

        $this->assertDatabaseCount('planteles', 0);
        $this->assertDatabaseCount('escuelas', 0);
    }
}
