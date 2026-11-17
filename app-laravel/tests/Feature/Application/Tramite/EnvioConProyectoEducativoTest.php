<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Tramite\EnviarTramite;
use App\Application\Tramite\ResumenTramite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/** En media superior el trámite no se completa ni se envía hasta confirmar el proyecto educativo. */
class EnvioConProyectoEducativoTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    public function test_media_superior_sin_confirmar_el_proyecto_no_esta_completo_ni_se_envia(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, nivelClave: 'media_superior');

        $this->assertFalse(app(ResumenTramite::class)->paraEscuela($escuela->id)->completo);

        try {
            app(EnviarTramite::class)->ejecutar($escuela->id, $escuela->solicitante->user_id);
            $this->fail('El envío debió rechazarse.');
        } catch (PrecondicionIncumplida) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_confirmado_el_proyecto_el_trámite_queda_completo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $nivel = $this->completarTramite($escuela, nivelClave: 'media_superior');

        (new MarcarPasoCompletado)->ejecutar($nivel->id, SeccionesProyectoEducativo::CLAVE_PASO);

        $this->assertTrue(app(ResumenTramite::class)->paraEscuela($escuela->id)->completo);
    }
}
