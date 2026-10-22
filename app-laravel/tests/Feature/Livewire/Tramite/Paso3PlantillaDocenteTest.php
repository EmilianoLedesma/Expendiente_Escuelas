<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso3\PlantillaDocente;
use App\Models\Personal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3PlantillaDocenteTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    /** @return array<string, string> */
    private function fila(int $cargoId, string $nombre, string $salaId = '', string $asignaturaId = ''): array
    {
        return [
            'cargoPuestoId' => (string) $cargoId, 'nombre' => $nombre, 'nacionalidad' => 'Mexicana', 'sexo' => 'F',
            'estudios' => 'Lic. en Educación', 'cedulaODocumento' => '1234567', 'salaId' => $salaId, 'asignaturaId' => $asignaturaId,
        ];
    }

    public function test_empieza_con_una_fila_vacia_y_permite_agregar_y_quitar(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlantillaDocente::class, ['escuelaNivel' => $escuelaNivel])
            ->assertCount('personas', 1)
            ->call('agregarPersona')
            ->assertCount('personas', 2)
            ->call('quitarPersona', 0)
            ->assertCount('personas', 1);
    }

    public function test_muestra_un_acordeon_con_una_sola_persona_abierta(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlantillaDocente::class, ['escuelaNivel' => $escuelaNivel])
            ->call('agregarPersona')
            ->assertSet('personaAbierta', 1)
            ->assertSeeHtml('aria-expanded="true"')
            ->assertSeeHtml('id="persona-panel-1"')
            ->assertDontSeeHtml('id="persona-panel-0"');
    }

    public function test_pagina_las_personas_para_limitar_el_scroll(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlantillaDocente::class, ['escuelaNivel' => $escuelaNivel])
            ->set('personas', array_fill(0, 6, $this->fila($this->cargoId('primaria', 'Docente Titular de Grupo'), 'Docente')))
            ->call('irAPagina', 2)
            ->assertSet('paginaPersonas', 2)
            ->assertSee('Persona 6')
            ->assertDontSee('Persona 1')
            ->assertSee('Página 2 de 2');
    }

    public function test_guarda_la_plantilla_y_continua_a_la_matricula(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlantillaDocente::class, ['escuelaNivel' => $escuelaNivel])
            ->set('personas', [
                $this->fila($this->cargoId('primaria', 'Director Técnico'), 'Ana López'),
                $this->fila($this->cargoId('primaria', 'Docente Titular de Grupo'), 'Luis Pérez'),
            ])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSessionHas('toast.titulo', 'Plantilla docente guardada')
            ->assertRedirect(route('tramite.paso3-matricula', ['escuelaNivel' => $escuelaNivel->id]));

        $this->assertSame(2, Personal::where('escuela_nivel_id', $escuelaNivel->id)->count());
    }

    public function test_muestra_errores_por_fila(): void
    {
        $escuelaNivel = $this->nivelListoPara('inicial', 'plantilla_docente');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(PlantillaDocente::class, ['escuelaNivel' => $escuelaNivel])
            ->set('personas', [$this->fila($this->cargoId('inicial', 'Responsable de Sala'), 'Ana López')])
            ->call('guardar')
            ->assertHasErrors(['personas.0.salaId'])
            ->assertNoRedirect();
    }

    public function test_al_volver_muestra_la_plantilla_capturada_con_su_sala(): void
    {
        $escuelaNivel = $this->nivelListoPara('inicial', 'plantilla_docente');
        $salaId = (string) DB::table('salas')->where('clave', 'maternal_a')->value('id');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);
        Livewire::test(PlantillaDocente::class, ['escuelaNivel' => $escuelaNivel])
            ->set('personas', [$this->fila($this->cargoId('inicial', 'Asistente Educativo'), 'Ana López', $salaId)])
            ->call('guardar');

        Livewire::test(PlantillaDocente::class, ['escuelaNivel' => $escuelaNivel->refresh()])
            ->assertSet('personas.0.nombre', 'Ana López')
            ->assertSet('personas.0.salaId', $salaId);
    }

    public function test_la_pagina_ofrece_cargos_del_nivel_y_asignaturas_en_secundaria(): void
    {
        $escuelaNivel = $this->nivelListoPara('secundaria', 'plantilla_docente');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        $this->get(route('tramite.paso3-plantilla', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSee('Plantilla docente')
            ->assertSee('Prefecto')
            ->assertDontSee('Responsable de Sala');

        Livewire::test(PlantillaDocente::class, ['escuelaNivel' => $escuelaNivel])
            ->set('personas.0.cargoPuestoId', (string) $this->cargoId('secundaria', 'Docente Titular'))
            ->assertSee('Asignatura que imparte')
            ->assertSee('Educación Física');
    }
}
