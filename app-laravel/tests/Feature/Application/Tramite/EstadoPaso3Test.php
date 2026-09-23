<?php

namespace Tests\Feature\Application\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Tramite\EstadoPaso3;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class EstadoPaso3Test extends TestCase
{
    use RefreshDatabase;

    private int $escuelaNivelId;

    protected function setUp(): void
    {
        parent::setUp();
        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        $this->escuelaNivelId = EscuelaNivel::create([
            'escuela_id' => $escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', 'primaria')->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ])->id;
    }

    private function completar(string ...$claves): void
    {
        foreach ($claves as $clave) {
            (new MarcarPasoCompletado)->ejecutar($this->escuelaNivelId, $clave);
        }
    }

    public function test_sin_avance_solo_el_primer_sub_paso_es_accesible(): void
    {
        $estado = app(EstadoPaso3::class);

        $this->assertSame('inmueble', $estado->primerPendiente($this->escuelaNivelId));
        $this->assertTrue($estado->puedeAcceder($this->escuelaNivelId, 'inmueble'));
        $this->assertFalse($estado->puedeAcceder($this->escuelaNivelId, 'infraestructura'));
        $this->assertFalse($estado->puedeAcceder($this->escuelaNivelId, 'mobiliario'));
    }

    public function test_un_sub_paso_es_accesible_si_el_anterior_esta_completado(): void
    {
        $this->completar('inmueble');
        $estado = app(EstadoPaso3::class);

        $this->assertSame('infraestructura', $estado->primerPendiente($this->escuelaNivelId));
        $this->assertTrue($estado->puedeAcceder($this->escuelaNivelId, 'inmueble'));
        $this->assertTrue($estado->puedeAcceder($this->escuelaNivelId, 'infraestructura'));
        $this->assertFalse($estado->puedeAcceder($this->escuelaNivelId, 'mobiliario'));
    }

    public function test_un_paso_en_progreso_no_cuenta_como_completado(): void
    {
        DB::table('escuela_nivel_pasos')->insert([
            'escuela_nivel_id' => $this->escuelaNivelId,
            'paso_captura_id' => DB::table('pasos_captura')->where('clave', 'inmueble')->value('id'),
            'estado' => 'en_progreso',
        ]);
        $estado = app(EstadoPaso3::class);

        $this->assertSame('inmueble', $estado->primerPendiente($this->escuelaNivelId));
        $this->assertFalse($estado->puedeAcceder($this->escuelaNivelId, 'infraestructura'));
    }

    /** El orden sale del catálogo: tras los 3 sub-pasos construidos, lo siguiente es plan_estudios (WS-8). */
    public function test_el_orden_se_deriva_del_catalogo_no_de_tres_pasos_fijos(): void
    {
        $this->completar('inmueble', 'infraestructura', 'mobiliario');
        $estado = app(EstadoPaso3::class);

        $this->assertSame('plan_estudios', $estado->primerPendiente($this->escuelaNivelId));
        $this->assertTrue($estado->puedeAcceder($this->escuelaNivelId, 'plan_estudios'));
        $this->assertFalse($estado->puedeAcceder($this->escuelaNivelId, 'plantilla_docente'));
    }

    public function test_sin_pendientes_devuelve_null(): void
    {
        $this->completar(...DB::table('pasos_captura')->orderBy('orden')->pluck('clave')->all());

        $this->assertNull(app(EstadoPaso3::class)->primerPendiente($this->escuelaNivelId));
    }

    public function test_una_clave_desconocida_es_un_error(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(EstadoPaso3::class)->puedeAcceder($this->escuelaNivelId, 'no_existe');
    }
}
