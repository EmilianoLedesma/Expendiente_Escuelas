<?php

namespace Tests\Feature\Tramite;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\ConTramiteDevuelto;
use Tests\TestCase;

/** Devolución: cada ruta que escribe se abre solo con su paso reabierto (403 si lo rechazado es otro paso). */
class RutasPorPasoTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use ConTramiteDevuelto;
    use RefreshDatabase;

    /** @return array<string, array{string, string, string}> ruta, parámetro, paso que la abre */
    public static function rutas(): array
    {
        return [
            'plantel' => ['tramite.plantel', 'escuela', 'plantel'],
            'paso2 responsable' => ['tramite.paso2', 'escuela', 'responsable'],
            'paso2 niveles' => ['tramite.paso2', 'escuela', 'niveles'],
            'paso2 documentos' => ['tramite.paso2-documentos', 'escuela', 'documentos'],
            'documentos del nivel' => ['tramite.paso2-nivel-documentos', 'escuelaNivel', 'documentos_nivel'],
            'inmueble' => ['tramite.paso3-inmueble', 'escuelaNivel', 'inmueble'],
            'infraestructura' => ['tramite.paso3-infraestructura', 'escuelaNivel', 'infraestructura'],
            'mobiliario' => ['tramite.paso3-mobiliario', 'escuelaNivel', 'mobiliario'],
            'plan de estudios' => ['tramite.paso3-plan-estudios', 'escuelaNivel', 'plan_estudios'],
            'plantilla docente' => ['tramite.paso3-plantilla', 'escuelaNivel', 'plantilla_docente'],
            'matricula' => ['tramite.paso3-matricula', 'escuelaNivel', 'matricula'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_ruta_abre_con_paso_reabierto_y_403_con_paso_aceptado(string $ruta, string $parametro, string $paso): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela);
        $dueno = $escuela->solicitante->user;
        $url = route($ruta, [$parametro => $parametro === 'escuela' ? $escuela->id : $nivel->id]);
        $otro = $paso === 'matricula' ? 'plantel' : 'matricula';

        $this->devolverTramite($escuela, [$nivel->id => [$otro => 'x']]);
        $this->actingAs($dueno)->get($url)->assertForbidden();

        $this->devolverTramite($escuela, [$nivel->id => [$paso => 'x']]);
        $this->assertNotSame(403, $this->actingAs($dueno)->get($url)->status());
    }
}
