<?php

namespace Tests\Feature\View\Components\Tramite;

use App\Application\Tramite\DTO\NivelDelTramite;
use App\Application\Tramite\DTO\ResumenTramiteDTO;
use App\Application\Tramite\DTO\SeccionTramite;
use DateTimeImmutable;
use Tests\TestCase;

class ComponentesTramiteTest extends TestCase
{
    private function seccion(string $estado, ?string $accion, ?string $href, ?string $motivo = null): SeccionTramite
    {
        return new SeccionTramite('responsable', 'Responsable legal', 'Descripción', $estado, $accion, $href, $motivo, 2, 4);
    }

    private function resumen(?string $nombre = 'Colegio Arcoíris'): ResumenTramiteDTO
    {
        $s = fn (string $clave, string $nombre, string $estado, ?string $accion = null, ?string $href = null, ?int $paso = null, ?int $total = null) => new SeccionTramite($clave, $nombre, '', $estado, $accion, $href, null, $paso, $total);

        return new ResumenTramiteDTO(
            escuelaId: 21,
            domicilio: 'Calle 1 #10, Centro, Querétaro, C.P. 76000',
            plantel: [],
            generales: [
                $s('plantel', 'Datos del plantel', 'completado', paso: 1, total: 4),
                $s('responsable', 'Responsable legal', 'completado', paso: 2, total: 4),
                $s('documentos', 'Documentos', 'completado', paso: 3, total: 4),
                $s('niveles', 'Niveles educativos', 'completado', paso: 4, total: 4),
            ],
            niveles: [new NivelDelTramite(7, 'inicial', 'Educación Inicial', [
                $s('inmueble', 'Datos del inmueble', 'completado', paso: 5, total: 7),
                $s('infraestructura', 'Infraestructura', 'completado', 'revisar', '/tramite/paso3/7/infraestructura', 6, 7),
                $s('mobiliario', 'Mobiliario', 'pendiente', 'comenzar', '/tramite/paso3/7/mobiliario', 7, 7),
                $s('plan_estudios', 'Plan de estudios', 'no_disponible'),
            ])],
            completo: false,
            nombre: $nombre,
            iniciadoEl: new DateTimeImmutable('2026-09-24'),
        );
    }

    public function test_el_recorrido_muestra_identidad_avance_y_la_seccion_actual(): void
    {
        $this->blade('<x-tramite.recorrido :resumen="$r" seccion-actual="mobiliario" :escuela-nivel-id="7" />', ['r' => $this->resumen()])
            ->assertSee('Colegio Arcoíris')
            ->assertSee('Trámite Nº 0021')
            ->assertSee('Iniciado el 24/09/2026')
            ->assertSee('6 de 7')
            ->assertSee('Educación Inicial')
            ->assertSee('bg-nivel-inicial-esc', false)
            ->assertSee('aria-current="step"', false)
            ->assertSee('Aquí estás')
            ->assertSee('href="/tramite/paso3/7/infraestructura"', false)
            ->assertSee('No disponible aún');
    }

    public function test_el_recorrido_se_pliega_en_movil_y_sin_nombre_lo_dice(): void
    {
        $this->blade('<x-tramite.recorrido :resumen="$r" seccion-actual="resumen" />', ['r' => $this->resumen(null)])
            ->assertSee('<details', false)
            ->assertSee('Secciones del trámite')
            ->assertSee('Sin nombre propuesto')
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('aria-current="step"', false);
    }

    /** Migrado de ProgresoTest::test_un_dot_clicable_se_distingue_visualmente_de_uno_de_solo_texto. */
    public function test_una_fila_alcanzable_tiene_accion_visible_y_nombre_accesible(): void
    {
        $this->blade('<ol><x-tramite.task-row :seccion="$s" numerado /></ol>', ['s' => $this->seccion('pendiente', 'comenzar', '/tramite/paso2/1')])
            ->assertSee('2. Responsable legal')
            ->assertSee('href="/tramite/paso2/1"', false)
            ->assertSee('text-primary underline', false)
            ->assertSee('Comenzar')
            ->assertSee('<span class="sr-only">: Responsable legal</span>', false)
            ->assertSee('data-estado="pendiente"', false);
    }

    public function test_una_fila_bloqueada_muestra_el_motivo_y_ningun_enlace(): void
    {
        $this->blade('<ol><x-tramite.task-row :seccion="$s" /></ol>', ['s' => $this->seccion('pendiente', null, null, 'Completa primero: Documentos')])
            ->assertSee('Completa primero: Documentos')
            ->assertDontSee('<a ', false);
    }

    public function test_un_nombre_de_archivo_largo_se_parte(): void
    {
        $nombre = str_repeat('a', 120).'.pdf';

        $this->blade(
            '<ul><x-tramite.documento-row clave="ine" titulo="INE" :escuela-id="1" :capturado="$c" :editable="false" accion="guardarDocumentoSimple(\'ine\')" /></ul>',
            ['c' => ['nombreArchivo' => $nombre, 'subidoEn' => now()]]
        )
            ->assertSee($nombre)
            ->assertSee('break-all', false)
            ->assertSee('Reemplazar');
    }

    public function test_nivel_como_punto_y_como_franja(): void
    {
        $this->blade('<x-tramite.nivel clave="primaria">Primaria</x-tramite.nivel>')
            ->assertSee('bg-nivel-primaria', false)
            ->assertSee('Primaria');

        $this->blade('<x-tramite.nivel clave="inicial" como="franja">x</x-tramite.nivel>')
            ->assertSee('border-l-4', false)
            ->assertSee('border-nivel-inicial-esc', false);
    }
}
