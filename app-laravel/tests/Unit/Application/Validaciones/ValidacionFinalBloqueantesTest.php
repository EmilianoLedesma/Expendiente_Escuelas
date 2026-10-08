<?php

namespace Tests\Unit\Application\Validaciones;

use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionCapacidad;
use App\Application\Validaciones\DTO\SeccionNivel;
use App\Application\Validaciones\DTO\ValidacionFinal;
use PHPUnit\Framework\TestCase;

/** WS-7a §3: the ready rule in one place. */
class ValidacionFinalBloqueantesTest extends TestCase
{
    private function fila(string $clave, string $estado, array $lineas = []): FilaValidacion
    {
        return new FilaValidacion($clave, "Título {$clave}", $estado, 'mensaje', [], $lineas);
    }

    public function test_bloquean_el_documental_no_cumple_y_la_capacidad_no_cumple_o_sin_capturar(): void
    {
        $motivos = ValidacionFinal::bloqueantes(
            [$this->fila('curp_coincide', 'no_cumple'), $this->fila('nombre_identidad_coincide', 'advertencia'), $this->fila('rfc_coincide', 'no_evaluable')],
            [new SeccionCapacidad(7, 'Primaria', [
                $this->fila('primaria.superficie.aulas', 'no_cumple'),
                $this->fila('primaria.superficie.predio_total', 'no_evaluable'),
                $this->fila('primaria.infraestructura.altura_aulas', 'no_verificable'),
                $this->fila('primaria.personal.director_tecnico', 'cumple'),
            ])],
            [new SeccionNivel(7, 'Primaria', [$this->fila('recibo_no_reutilizado', 'no_cumple')])],
        );

        $this->assertSame([
            'Título curp_coincide',
            'Primaria: Título recibo_no_reutilizado',
            'Capacidad instalada · Primaria: Título primaria.superficie.aulas',
            'Capacidad instalada · Primaria: Título primaria.superficie.predio_total',
        ], $motivos);
    }

    public function test_los_no_verificables_y_el_mobiliario_con_conceptos_no_verificados_no_bloquean(): void
    {
        $this->assertSame([], ValidacionFinal::bloqueantes([], [new SeccionCapacidad(3, 'Inicial', [
            $this->fila('inicial.superficie.aula_lactantes', 'no_verificable'),
            $this->fila('inicial.mobiliario', 'cumple', ['No verificados (sala de usos múltiples): Colchonetas']),
        ])], []));
    }
}
