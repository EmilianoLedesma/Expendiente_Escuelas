<?php

namespace Tests\Feature\Application\Revision;

use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Application\Revision\ContenidoSeccionProyecto;
use App\Application\Revision\RolesSedeq;
use App\Models\ProyectoEducativoFormacionAccion;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Sección 11 para el evaluador: la acción formativa trae `dias` como lista y debe salir como texto legible. */
class ContenidoSeccionFormacionDocenteTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    public function test_los_dias_de_una_accion_salen_como_lista_legible_sin_romper(): void
    {
        (new RolesSeeder)->run();
        $evaluador = User::factory()->create()->assignRole(RolesSedeq::EVALUADOR_PROYECTO);
        $nivel = $this->nivelDe(Solicitante::factory()->create(), 'media_superior', 'en_revision');
        ProyectoEducativoFormacionAccion::create([
            'escuela_nivel_id' => $nivel->id, 'tipo' => 'inicial', 'orden' => 1, 'nombre' => 'Taller de planeación',
            'proposito' => 'p', 'participantes' => 'q', 'formato' => 'taller', 'duracion_horas' => 10,
            'dias' => ['lunes', 'miercoles', 'viernes'], 'hora_inicio' => '09:00', 'hora_fin' => '11:00',
            'fecha_inicio' => '2026-01-05', 'fecha_fin' => '2026-01-30', 'temario' => 't', 'evaluacion' => 'e',
            'recursos' => 'r', 'perfil_ponente' => 'pp', 'reconocimiento' => 'rc',
        ]);

        $c = app(ContenidoSeccionProyecto::class)->ejecutar($evaluador->id, $nivel->id, SeccionesProyectoEducativo::formacion());

        $filas = collect($c['listas'])->firstWhere('titulo', 'Acciones')['filas'][0];
        $this->assertContains(['Dias', 'Lunes, Miércoles, Viernes'], $filas);
    }
}
