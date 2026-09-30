<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\EscuelaNiveles\RegistrarDatosNivel;
use App\Application\Tramite\EstadoPaso24;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\TestCase;

/**
 * WS-5b: con Paso 2 completo, cada página de Paso 3 de un nivel manda a su
 * Paso 2.4 hasta que esté completo; los demás niveles no se ven afectados.
 * Round-trip HTTP real (ADR-003).
 */
class Paso3RequierePaso24CompletoTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->completarPaso2($this->escuela->id);
    }

    private function nivel(string $clave): EscuelaNivel
    {
        return EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    /** @return array<string, array{string}> Lista estática (ver Paso3RequierePaso2CompletoTest). */
    public static function rutasConCompuerta(): array
    {
        return [
            'tramite.paso3-inmueble' => ['tramite.paso3-inmueble'],
            'tramite.paso3-infraestructura' => ['tramite.paso3-infraestructura'],
            'tramite.paso3-mobiliario' => ['tramite.paso3-mobiliario'],
        ];
    }

    #[DataProvider('rutasConCompuerta')]
    public function test_paso3_redirige_a_los_documentos_del_nivel_si_paso24_esta_incompleto(string $ruta): void
    {
        $preescolar = $this->nivel('preescolar');

        $this->actingAs($this->solicitante->user)
            ->get(route($ruta, ['escuelaNivel' => $preescolar->id]))
            ->assertRedirect(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $preescolar->id]));

        $this->assertDatabaseCount('escuela_nivel_pasos', 0);
    }

    /** Review Focus 1. */
    public function test_un_nivel_sin_paso24_no_afecta_a_otro_nivel_con_paso24_completo(): void
    {
        $primaria = $this->nivel('primaria');
        $this->completarPaso24($primaria->id);
        $secundaria = $this->nivel('secundaria');

        $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $primaria->id]))
            ->assertOk()
            ->assertSeeLivewire('tramite.paso3.datos-inmueble');

        $this->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $secundaria->id]))
            ->assertRedirect(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $secundaria->id]));
    }

    /** Review Focus 3. */
    public function test_reemplazar_el_formato_firmado_con_paso3_empezado_no_vuelve_a_bloquear_paso3(): void
    {
        $primaria = $this->nivel('primaria');
        $this->completarPaso24($primaria->id);
        (new MarcarPasoCompletado)->ejecutar($primaria->id, 'inmueble');

        app(RegistrarDocumento::class)->ejecutar($this->escuela->id, 'formato_solicitud', UploadedFile::fake()->create('firmado-v2.pdf', 10, 'application/pdf'), new DatosDocumento, $primaria->id);

        $this->assertNull(app(EstadoPaso24::class)->etapaFaltante($primaria->id));
        $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]))
            ->assertOk();
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $primaria->id,
            'paso_captura_id' => DB::table('pasos_captura')->where('clave', 'inmueble')->value('id'),
            'estado' => 'completado',
        ]);
    }

    /** Review Focus 4 (decisión del dueño): cambiar el turno descarta el Formato y vuelve a bloquear el Paso 3 del nivel. */
    public function test_cambiar_el_turno_con_paso3_empezado_vuelve_a_bloquear_paso3(): void
    {
        $primaria = $this->nivel('primaria');
        $this->completarPaso24($primaria->id);
        (new MarcarPasoCompletado)->ejecutar($primaria->id, 'inmueble');

        app(RegistrarDatosNivel::class)->ejecutar($primaria->id, 'vespertino', 'mixto');

        $this->assertSame(EstadoPaso24::DOCUMENTOS_NIVEL, app(EstadoPaso24::class)->etapaFaltante($primaria->id));
        $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]))
            ->assertRedirect(route('tramite.paso2-nivel-documentos', ['escuelaNivel' => $primaria->id]));
    }
}
