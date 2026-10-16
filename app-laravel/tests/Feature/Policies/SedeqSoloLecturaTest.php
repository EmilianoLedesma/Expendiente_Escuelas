<?php

namespace Tests\Feature\Policies;

use App\Application\Tramite\TramiteEditable;
use App\Models\AsignacionRevision;
use App\Models\EscuelaNivel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\ConTramitesEnRevision;
use Tests\TestCase;

/** Anti-goal del HUD: nadie del lado SEDEQ cambia datos del trámite. Abrir `view` no abre ninguna escritura. */
class SedeqSoloLecturaTest extends TestCase
{
    use ConTramitesEnRevision;
    use RefreshDatabase;

    private const HABILIDADES_DE_ESCRITURA = '/^can:(update|updateInmueble|gestionarResponsables|delete),/';

    private EscuelaNivel $nivel;

    private User $supervisorUsuario;

    private User $revisorUsuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nivel = $this->nivelEnRevision();
        $this->supervisorUsuario = $this->supervisor();
        $this->revisorUsuario = $this->revisor();
        AsignacionRevision::create(['escuela_nivel_id' => $this->nivel->id, 'revisor_id' => $this->revisorUsuario->id, 'asignado_por' => $this->supervisorUsuario->id, 'asignado_at' => now()]);
    }

    /** @return list<Ruta> */
    private function rutasDeEscritura(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (Ruta $ruta) => collect($ruta->gatherMiddleware())->contains(fn ($m) => is_string($m) && preg_match(self::HABILIDADES_DE_ESCRITURA, $m) === 1),
        ));
    }

    /** @return array<string, int|string> */
    private function parametros(): array
    {
        return [
            'escuela' => $this->nivel->escuela_id,
            'escuelaNivel' => $this->nivel->id,
            'clave' => 'ine',
            'evaluacion' => (int) DB::table('evaluaciones_validacion')->insertGetId(['escuela_id' => $this->nivel->escuela_id, 'archivo_path' => 'r.pdf', 'lista_para_envio' => true, 'resultados' => '[]']),
        ];
    }

    public function test_supervisor_y_revisor_reciben_403_en_cada_ruta_que_escribe(): void
    {
        $rutas = $this->rutasDeEscritura();
        $this->assertGreaterThanOrEqual(12, count($rutas), 'La lista de rutas de escritura salió casi vacía: ¿cambió el formato del middleware?');
        $parametros = $this->parametros();

        foreach ([$this->supervisorUsuario, $this->revisorUsuario] as $usuario) {
            $this->actingAs($usuario);
            foreach ($rutas as $ruta) {
                $faltan = array_diff($ruta->parameterNames(), array_keys($parametros));
                $this->assertSame([], $faltan, "Ruta {$ruta->uri()}: agrega un fixture para ".implode(', ', $faltan));
                $url = url(str_replace(
                    array_map(fn ($p) => '{'.$p.'}', array_keys($parametros)),
                    array_map('strval', $parametros),
                    $ruta->uri(),
                ));

                foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
                    $this->call($metodo, $url)->assertForbidden();
                }
            }
        }
    }

    public function test_las_habilidades_de_escritura_siguen_cerradas_para_sedeq(): void
    {
        $escuela = $this->nivel->escuela;
        // Trámite "editable" a la fuerza: así el false se debe a la propiedad, no a que ya se envió.
        $this->mock(TramiteEditable::class, fn ($m) => $m->shouldReceive('esEditable')->andReturn(true));
        $this->assertTrue(Gate::forUser($escuela->solicitante->user)->allows('update', $this->nivel), 'El fake debe abrir update al dueño.');

        foreach ([$this->supervisorUsuario, $this->revisorUsuario] as $usuario) {
            $gate = Gate::forUser($usuario);
            $this->assertTrue($gate->allows('view', $escuela));
            $this->assertTrue($gate->allows('view', $this->nivel));
            foreach (['update', 'delete', 'verResumen', 'verReporte'] as $habilidad) {
                $this->assertFalse($gate->allows($habilidad, $escuela), "EscuelaPolicy::{$habilidad}");
            }
            foreach (['update', 'updateInmueble', 'gestionarResponsables'] as $habilidad) {
                $this->assertFalse($gate->allows($habilidad, $this->nivel), "EscuelaNivelPolicy::{$habilidad}");
            }
        }
    }
}
