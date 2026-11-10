<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Livewire\Tramite\Paso3\Matricula;
use App\Livewire\Tramite\Paso3\ProyectoEducativoIndice;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

class Paso3ProyectoEducativoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    public function test_antes_de_matricula_redirige_al_paso_pendiente(): void
    {
        $escuelaNivel = $this->nivelListoPara('media_superior', 'matricula');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(ProyectoEducativoIndice::class, ['escuelaNivel' => $escuelaNivel])
            ->assertRedirect(route('tramite.paso3-matricula', ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_indice_lista_las_veinte_secciones_con_enlace(): void
    {
        $escuelaNivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);
        $slugs = array_keys(SeccionesProyectoEducativo::todas());

        $this->get(route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSee('Presentación del Proyecto Educativo')
            ->assertSee('Filosofía Institucional')
            ->assertSee('Bibliografía del Proyecto')
            ->assertSee(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $slugs[1]]), false);
        $this->assertCount(20, $slugs);
    }

    public function test_nivel_que_no_aplica_da_404(): void
    {
        $escuelaNivel = $this->nivelListoPara('primaria', 'matricula');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        $this->get(route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]))->assertNotFound();
    }

    public function test_seccion_muestra_el_placeholder(): void
    {
        $escuelaNivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);
        $slug = array_key_first(SeccionesProyectoEducativo::todas());

        $this->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $slug]))
            ->assertOk()
            ->assertSee('Presentación del Proyecto Académico')
            ->assertSee('Contenido por definir');
    }

    public function test_seccion_desconocida_da_404(): void
    {
        $escuelaNivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        $this->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => 'no-existe']))->assertNotFound();
    }

    public function test_otro_solicitante_y_tramite_enviado_dan_403(): void
    {
        $escuelaNivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $ruta = route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]);

        $this->actingAs(Solicitante::factory()->create()->user)->get($ruta)->assertForbidden();

        $escuelaNivel->update(['estado_id' => DB::table('estados_expediente')->where('clave', 'en_revision')->value('id')]);
        $this->actingAs($escuelaNivel->escuela->solicitante->user)->get($ruta)->assertForbidden();
    }

    public function test_matricula_en_media_superior_continua_al_proyecto_educativo(): void
    {
        $escuelaNivel = $this->nivelListoPara('media_superior', 'matricula');
        $gradoId = DB::table('grados')->insertGetId(['nivel_educativo_id' => $escuelaNivel->nivel_educativo_id, 'nombre' => 'Primer semestre', 'orden' => 1]);
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(Matricula::class, ['escuelaNivel' => $escuelaNivel])
            ->set('grupos', [['gradoId' => (string) $gradoId, 'grupo' => 'A', 'alumnos' => '20']])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSessionHas('toast.titulo', 'Matrícula guardada')
            ->assertRedirect(route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_indice_explica_que_el_contenido_no_esta_disponible_y_marca_pendientes(): void
    {
        $escuelaNivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        $html = $this->get(route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]))
            ->assertOk()
            ->assertSee('El contenido de estas secciones aún no está disponible. Tu trámite podrá enviarse cuando puedas completarlas.')
            ->getContent();

        $this->assertSame(20, substr_count($html, 'Pendiente'));
    }

    public function test_sin_la_fila_del_catalogo_matricula_no_redirige_al_proyecto_y_las_paginas_dan_404(): void
    {
        $escuelaNivel = $this->nivelListoPara('media_superior', 'matricula');
        $gradoId = DB::table('grados')->insertGetId(['nivel_educativo_id' => $escuelaNivel->nivel_educativo_id, 'nombre' => 'Primer semestre', 'orden' => 1]);
        DB::table('pasos_captura')->where('clave', 'proyecto_educativo')->delete();
        $this->actingAs($escuelaNivel->escuela->solicitante->user);

        Livewire::test(Matricula::class, ['escuelaNivel' => $escuelaNivel])
            ->set('grupos', [['gradoId' => (string) $gradoId, 'grupo' => 'A', 'alumnos' => '20']])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.resumen', ['escuela' => $escuelaNivel->escuela_id]));

        $this->get(route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]))->assertNotFound();
        $slug = array_key_first(SeccionesProyectoEducativo::todas());
        $this->get(route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $slug]))->assertNotFound();
    }

    public function test_con_paso2_incompleto_las_paginas_redirigen_a_paso2(): void
    {
        $this->seed(DatabaseSeeder::class);
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'media_superior')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
        $slug = array_key_first(SeccionesProyectoEducativo::todas());

        foreach ([
            route('tramite.paso3-proyecto-educativo', ['escuelaNivel' => $escuelaNivel->id]),
            route('tramite.paso3-proyecto-educativo-seccion', ['escuelaNivel' => $escuelaNivel->id, 'seccion' => $slug]),
        ] as $ruta) {
            $this->actingAs($solicitante->user)->get($ruta)->assertRedirect(route('tramite.paso2', ['escuela' => $escuela->id]));
        }
    }
}
