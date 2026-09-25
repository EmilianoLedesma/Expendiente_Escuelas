<?php

namespace Tests\Feature\View\Components\Tramite;

use App\Application\Tramite\DTO\SeccionTramite;
use Tests\TestCase;

class ComponentesTramiteTest extends TestCase
{
    private function seccion(string $estado, ?string $accion, ?string $href, ?string $motivo = null): SeccionTramite
    {
        return new SeccionTramite('responsable', 'Responsable legal', 'Descripción', $estado, $accion, $href, $motivo, 2, 4);
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
