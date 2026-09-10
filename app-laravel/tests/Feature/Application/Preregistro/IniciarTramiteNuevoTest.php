<?php

namespace Tests\Feature\Application\Preregistro;

use App\Application\Preregistro\DTO\DatosPreregistro;
use App\Application\Preregistro\IniciarTramiteNuevo;
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
        $plantel = Plantel::create([
            'calle' => 'Calle Ya Registrada 5',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);

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
        $plantel = Plantel::create([
            'calle' => 'Calle Ya Registrada 5',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);

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
        $plantel = Plantel::create([
            'calle' => 'Calle Ya Registrada 5',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);

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

    public function test_dos_solicitantes_distintos_obtienen_escuelas_distintas_en_el_mismo_plantel(): void
    {
        $plantel = Plantel::create([
            'calle' => 'Calle Compartida 1',
            'colonia' => 'Centro',
            'municipio' => 'Querétaro',
            'codigo_postal' => '76000',
        ]);
        $solicitanteA = Solicitante::factory()->create();
        $solicitanteB = Solicitante::factory()->create();

        $resultadoA = (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'existente',
            plantelId: $plantel->id,
        ), $solicitanteA->id);

        $resultadoB = (new IniciarTramiteNuevo)->ejecutar(new DatosPreregistro(
            bifurcacion: 'existente',
            plantelId: $plantel->id,
        ), $solicitanteB->id);

        $this->assertNotSame($resultadoA->escuelaId, $resultadoB->escuelaId);
        $this->assertDatabaseCount('escuelas', 2);
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
