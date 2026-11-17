<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Livewire\Tramite\Paso3\ProyectoEducativoIndice;
use App\Livewire\Tramite\Paso3\ProyectoEducativoSeccion;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaPaso3;
use Tests\TestCase;

/** Tras una devolución, el solicitante ve qué secciones del proyecto educativo rechazó el evaluador y por qué. */
class Paso3DevolucionProyectoTest extends TestCase
{
    use PreparaPaso3;
    use RefreshDatabase;

    private const MOTIVO = 'Falta describir los instrumentos de evaluación.';

    private function nivelDevuelto(bool $conRechazo = true): EscuelaNivel
    {
        $nivel = $this->nivelListoPara('media_superior', 'proyecto_educativo');
        $this->actingAs($nivel->escuela->solicitante->user);
        DB::table('escuela_niveles')->where('id', $nivel->id)->update([
            'estado_id' => DB::table('estados_expediente')->where('clave', 'con_observaciones')->value('id'),
        ]);
        if ($conRechazo) {
            DB::table('decisiones_revision')->insert([
                'escuela_nivel_id' => $nivel->id, 'paso_clave' => 'pe_05', 'estado' => 'rechazado', 'motivo' => self::MOTIVO,
                'decidido_por' => User::factory()->create()->id, 'decidido_at' => now(),
            ]);
        }

        return $nivel->fresh();
    }

    public function test_el_indice_marca_la_seccion_rechazada_con_su_motivo(): void
    {
        $nivel = $this->nivelDevuelto();

        Livewire::test(ProyectoEducativoIndice::class, ['escuelaNivel' => $nivel])
            ->assertSee('Con observaciones')
            ->assertSee(self::MOTIVO);
    }

    public function test_sin_rechazos_el_indice_no_muestra_observaciones(): void
    {
        $nivel = $this->nivelDevuelto(conRechazo: false);

        Livewire::test(ProyectoEducativoIndice::class, ['escuelaNivel' => $nivel])
            ->assertDontSee('Con observaciones')
            ->assertDontSee(self::MOTIVO);
    }

    public function test_la_pagina_de_la_seccion_rechazada_muestra_el_motivo_y_las_demas_no(): void
    {
        $nivel = $this->nivelDevuelto();

        Livewire::test(ProyectoEducativoSeccion::class, ['escuelaNivel' => $nivel, 'seccion' => SeccionesProyectoEducativo::evaluacion()])
            ->assertSee('Observación del evaluador')
            ->assertSee(self::MOTIVO);

        Livewire::test(ProyectoEducativoSeccion::class, ['escuelaNivel' => $nivel, 'seccion' => SeccionesProyectoEducativo::objetivos()])
            ->assertDontSee('Observación del evaluador')
            ->assertDontSee(self::MOTIVO);
    }
}
