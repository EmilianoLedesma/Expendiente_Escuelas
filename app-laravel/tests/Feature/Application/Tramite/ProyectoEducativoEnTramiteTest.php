<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\ConsultarTramiteSedeq;
use App\Application\Tramite\DTO\SeccionTramite;
use App\Application\Tramite\EnviarTramite;
use App\Application\Tramite\EstadoPaso3;
use App\Application\Tramite\ResumenTramite;
use App\Models\EscuelaNivel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

/** "Presentación del Proyecto Educativo": sub-paso 7 del catálogo, solo de media superior. */
class ProyectoEducativoEnTramiteTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use PreparaPaso3;
    use RefreshDatabase;

    private const NOMBRE = 'Presentación del Proyecto Educativo';

    /** @return list<SeccionTramite> */
    private function secciones(EscuelaNivel $nivel): array
    {
        $dto = app(ResumenTramite::class)->paraEscuela($nivel->escuela_id);

        return $dto->niveles[0]->secciones;
    }

    /** @return list<array{nombre: string, estado: string}> */
    private function pasosSedeq(EscuelaNivel $nivel): array
    {
        return (new ReflectionMethod(ConsultarTramiteSedeq::class, 'pasos'))
            ->invoke(app(ConsultarTramiteSedeq::class), $nivel->id, $nivel->nivelEducativo->clave);
    }

    public function test_primaria_con_todo_completo_no_tiene_paso_pendiente(): void
    {
        $nivel = $this->nivelListoPara('primaria', 'proyecto_educativo');
        $estado = app(EstadoPaso3::class);

        $this->assertNull($estado->primerPendiente($nivel->id));

        $this->expectException(InvalidArgumentException::class);
        $estado->puedeAcceder($nivel->id, 'proyecto_educativo');
    }

    public function test_media_superior_alcanza_el_paso_solo_tras_matricula(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');
        $estado = app(EstadoPaso3::class);

        $this->assertFalse($estado->puedeAcceder($nivel->id, 'proyecto_educativo'));

        (new MarcarPasoCompletado)->ejecutar($nivel->id, 'matricula');

        $this->assertTrue($estado->puedeAcceder($nivel->id, 'proyecto_educativo'));
        $this->assertSame('proyecto_educativo', $estado->primerPendiente($nivel->id));
    }

    public function test_resumen_media_superior_incluye_la_seccion(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'matricula');
        $secciones = collect($this->secciones($nivel));
        $seccion = $secciones->firstWhere('clave', 'proyecto_educativo');

        $this->assertNotNull($seccion);
        $this->assertSame(self::NOMBRE, $seccion->nombre);
        $this->assertSame('pendiente', $seccion->estado);
        $this->assertSame('Completa primero: Matrícula', $seccion->motivoBloqueo);
        $this->assertSame([11], $secciones->pluck('totalPasos')->filter()->unique()->values()->all());
    }

    public function test_resumen_primaria_no_cambia(): void
    {
        $nivel = $this->nivelListoPara('primaria', 'proyecto_educativo');
        $secciones = collect($this->secciones($nivel));

        $this->assertNull($secciones->firstWhere('clave', 'proyecto_educativo'));
        $this->assertSame([10], $secciones->pluck('totalPasos')->filter()->unique()->values()->all());
    }

    public function test_tramite_de_media_superior_con_todo_lo_demas_no_se_puede_enviar(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela, 'fisica', [], 'media_superior');

        $dto = app(ResumenTramite::class)->paraEscuela($escuela->id);
        $seccion = collect($dto->niveles[0]->secciones)->firstWhere('clave', 'proyecto_educativo');

        $this->assertSame('pendiente', $seccion->estado);
        $this->assertSame(
            ['proyecto_educativo'],
            collect($dto->niveles[0]->secciones)->whereNotIn('estado', ['completado', 'no_aplica'])->pluck('clave')->values()->all(),
        );
        $this->assertFalse($dto->completo);

        $this->expectException(PrecondicionIncumplida::class);
        app(EnviarTramite::class)->ejecutar($escuela->id, $escuela->solicitante->user_id);
    }

    public function test_el_mismo_tramite_en_primaria_queda_completo(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);

        $this->assertTrue(app(ResumenTramite::class)->paraEscuela($escuela->id)->completo);
    }

    public function test_sedeq_lista_el_paso_solo_en_media_superior(): void
    {
        $media = $this->nivelListoPara('media_superior', 'matricula');
        $this->assertContains(['nombre' => self::NOMBRE, 'estado' => 'pendiente'], $this->pasosSedeq($media));

        $primaria = $this->nivelListoPara('primaria', 'proyecto_educativo');
        $this->assertNotContains(self::NOMBRE, array_column($this->pasosSedeq($primaria), 'nombre'));
    }
}
