<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\DTO\DatosPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\EstadoSeccionesProyecto;
use App\Application\ProyectoEducativo\GuardarPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class EstadoSeccionesProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    public function test_devuelve_las_20_secciones_todas_pendientes_sin_captura(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $completas = app(EstadoSeccionesProyecto::class)->completas($nivel->id);

        $this->assertSame(array_keys(SeccionesProyectoEducativo::todas()), array_keys($completas));
        $this->assertNotContains(true, $completas);
    }

    public function test_marca_completa_la_seccion_guardada(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        app(GuardarPerfilDocenteProyecto::class)->ejecutar($nivel->id, new DatosPerfilDocenteProyecto(trim(str_repeat('palabra ', 200))));

        $completas = app(EstadoSeccionesProyecto::class)->completas($nivel->id);

        $this->assertTrue($completas[SeccionesProyectoEducativo::perfilDocente()]);
        $this->assertFalse($completas[SeccionesProyectoEducativo::egreso()]);
    }
}
