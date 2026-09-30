<?php

namespace Tests\Unit\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Reglas\DocumentosRequeridosPresentes;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use PHPUnit\Framework\TestCase;

class DocumentosRequeridosPresentesTest extends TestCase
{
    public function test_cumple_cuando_todos_los_requeridos_estan_presentes(): void
    {
        $contexto = new ContextoValidacion('fisica', [], ['ine', 'acta_nacimiento'], ['acta_nacimiento', 'ine']);

        $resultado = (new DocumentosRequeridosPresentes)->evaluar($contexto);

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('documentos_requeridos_presentes', $resultado->clave);
    }

    public function test_no_cumple_y_lista_los_faltantes_en_orden_de_requeridos(): void
    {
        $contexto = new ContextoValidacion('fisica', [], ['ine', 'acta_nacimiento', 'dictamen_uso_suelo'], ['acta_nacimiento']);

        $resultado = (new DocumentosRequeridosPresentes)->evaluar($contexto);

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame(['ine', 'dictamen_uso_suelo'], $resultado->detalles['faltantes']);
    }

    public function test_un_documento_presente_que_no_se_requiere_no_compensa_uno_faltante(): void
    {
        $contexto = new ContextoValidacion('fisica', [], ['ine'], ['acta_constitutiva']);

        $resultado = (new DocumentosRequeridosPresentes)->evaluar($contexto);

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame(['ine'], $resultado->detalles['faltantes']);
    }

    public function test_no_evaluable_si_no_hay_requeridos(): void
    {
        // An empty applicable list can only mean an unseeded catalog
        // (DocumentosCompletos throws in that case) — never a silent pass.
        $resultado = (new DocumentosRequeridosPresentes)->evaluar(new ContextoValidacion('fisica', [], [], []));

        $this->assertSame(EstadoResultado::NoEvaluable, $resultado->estado);
    }
}
