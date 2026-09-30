<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Livewire\Tramite\Paso3\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3MatriculaTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private function gradoId(string $nivel, int $orden): string
    {
        return (string) DB::table('grados')->join('niveles_educativos', 'niveles_educativos.id', '=', 'grados.nivel_educativo_id')
            ->where('niveles_educativos.clave', $nivel)->where('grados.orden', $orden)->value('grados.id');
    }

    public function test_sin_plantilla_redirige_al_paso_pendiente(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'plantilla_docente');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(Matricula::class, ['escuelaNivel' => $escuelaNivel])
            ->assertRedirect(route('tramite.paso3-plantilla', ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_primaria_captura_grupos_y_regresa_al_resumen(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(Matricula::class, ['escuelaNivel' => $escuelaNivel])
            ->set('grupos', [
                ['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'A', 'alumnos' => '25'],
                ['gradoId' => $this->gradoId('primaria', 2), 'grupo' => 'A', 'alumnos' => '30'],
            ])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id]));

        $this->assertSame(55, (int) DB::table('matricula_grados')->where('escuela_nivel_id', $escuelaNivel->id)->sum('cantidad_alumnos'));
    }

    public function test_inicial_captura_por_sala(): void
    {
        $escuelaNivel = $this->nivelListoPara('inicial', 'matricula');
        $salaId = (int) DB::table('salas')->where('clave', 'lactantes_a')->value('id');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(Matricula::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSee('Lactantes A')
            ->set("salas.{$salaId}", '8')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('matricula_salas', ['escuela_nivel_id' => $escuelaNivel->id, 'sala_id' => $salaId, 'cantidad_alumnos' => 8]);
    }

    public function test_sin_alumnos_muestra_el_error(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(Matricula::class, ['escuelaNivel' => $escuelaNivel])
            ->set('grupos', [['gradoId' => $this->gradoId('primaria', 1), 'grupo' => 'A', 'alumnos' => '0']])
            ->call('guardar')
            ->assertHasErrors(['matricula']);
    }

    public function test_la_etiqueta_depende_del_tipo_de_tramite_y_al_volver_precarga(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'matricula');
        $escuelaNivel->update(['tipo_tramite' => 'reincorporacion']);
        $this->actingAs($escuelaNivel->escuela->solicitante->user);
        Livewire::test(Matricula::class, ['escuelaNivel' => $escuelaNivel])
            ->set('grupos', [['gradoId' => $this->gradoId('primaria', 3), 'grupo' => 'B', 'alumnos' => '20']])
            ->call('guardar');

        $this->get(route('tramite.paso3-matricula', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSee('Alumnos inscritos');

        Livewire::test(Matricula::class, ['escuelaNivel' => $escuelaNivel])
            ->assertSet('grupos.0.grupo', 'B')
            ->assertSet('grupos.0.alumnos', '20');
    }
}
