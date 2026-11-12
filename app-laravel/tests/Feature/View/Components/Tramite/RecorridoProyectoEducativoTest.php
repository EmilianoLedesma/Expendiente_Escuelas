<?php

namespace Tests\Feature\View\Components\Tramite;

use App\Application\ProyectoEducativo\DTO\DatosPerfilDocenteProyecto;
use App\Application\ProyectoEducativo\GuardarPerfilDocenteProyecto;
use App\Application\Tramite\DTO\NivelDelTramite;
use App\Application\Tramite\DTO\ResumenTramiteDTO;
use App\Application\Tramite\DTO\SeccionTramite;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class RecorridoProyectoEducativoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function resumen(int $escuelaNivelId, string $nivelClave): ResumenTramiteDTO
    {
        $s = fn (string $clave, string $nombre, ?string $href) => new SeccionTramite($clave, $nombre, '', 'en_curso', 'continuar', $href, null, 7, 8);

        return new ResumenTramiteDTO(
            escuelaId: 21,
            domicilio: 'Calle 1',
            plantel: [],
            generales: [],
            niveles: [new NivelDelTramite($escuelaNivelId, $nivelClave, 'Nivel', [
                $s('proyecto_educativo', 'Presentación del Proyecto Educativo', "/tramite/paso3/{$escuelaNivelId}/proyecto-educativo"),
            ])],
            completo: false,
            nombre: 'Colegio',
            iniciadoEl: new DateTimeImmutable('2026-11-12'),
        );
    }

    public function test_lista_las_20_secciones_con_enlace_y_marca_las_completas(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        app(GuardarPerfilDocenteProyecto::class)->ejecutar($nivel->id, new DatosPerfilDocenteProyecto(trim(str_repeat('palabra ', 200))));

        $this->blade('<x-tramite.recorrido :resumen="$r" seccion-actual="resumen" />', ['r' => $this->resumen($nivel->id, 'media_superior')])
            ->assertSee('Perfil Docente')
            ->assertSee('Bibliografía del Proyecto')
            ->assertSee("/tramite/paso3/{$nivel->id}/proyecto-educativo/perfil-docente", false)
            ->assertSee('(completada)')
            ->assertSee('data-subseccion-hecha', false);
    }

    public function test_el_submenu_es_plegable_y_cerrado_fuera_del_paso(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->blade('<x-tramite.recorrido :resumen="$r" seccion-actual="resumen" />', ['r' => $this->resumen($nivel->id, 'media_superior')])
            ->assertSee('<details data-subsecciones', false)
            ->assertDontSee('data-subsecciones open', false);
    }

    public function test_el_submenu_abre_y_marca_la_seccion_actual_dentro_del_paso(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->blade(
            '<x-tramite.recorrido :resumen="$r" seccion-actual="proyecto_educativo" :escuela-nivel-id="$id" subseccion-actual="perfil-docente" />',
            ['r' => $this->resumen($nivel->id, 'media_superior'), 'id' => $nivel->id],
        )
            ->assertSee('data-subsecciones open', false)
            ->assertSee('data-subseccion-actual', false);
    }

    public function test_otros_niveles_no_llevan_submenu(): void
    {
        $nivel = $this->nivelListoPara('inicial', 'proyecto_educativo');

        $this->blade('<x-tramite.recorrido :resumen="$r" seccion-actual="resumen" />', ['r' => $this->resumen($nivel->id, 'inicial')])
            ->assertDontSee('data-subsecciones', false);
    }

    public function test_el_menu_lateral_se_desplaza_por_separado_en_escritorio(): void
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');

        $this->blade('<x-tramite.recorrido :resumen="$r" seccion-actual="resumen" />', ['r' => $this->resumen($nivel->id, 'media_superior')])
            ->assertSee('lg:sticky', false)
            ->assertSee('lg:overflow-y-auto', false)
            ->assertSee('scroll-minimal', false);
    }
}
