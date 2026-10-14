<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Tramite\Paso2Responsable;
use App\Models\AulaNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\ConNivelParaResponsables;
use Tests\TestCase;

/** WS-7b-1 U3 (presentación): agregar y quitar niveles hasta el envío. */
class Paso2NivelesEdicionTest extends TestCase
{
    use CompletaPaso2;
    use ConNivelParaResponsables;
    use RefreshDatabase;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();
        (new CatalogoMinimoSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        $this->actingAs($solicitante->user);
    }

    private function id(string $clave): int
    {
        return (int) NivelEducativo::where('clave', $clave)->value('id');
    }

    /** Paso 2 completo y los niveles dados ya elegidos. */
    private function conNiveles(string ...$claves): void
    {
        $this->completarPaso2($this->escuela->id);
        app(RegistrarNivelesSeleccionados::class)->ejecutar($this->escuela->id, agregar: array_map($this->id(...), $claves));
    }

    private function nivel(string $clave): EscuelaNivel
    {
        return EscuelaNivel::where('escuela_id', $this->escuela->id)->where('nivel_educativo_id', $this->id($clave))->firstOrFail();
    }

    private function pagina()
    {
        return Livewire::withQueryParams(['seccion' => 'niveles'])->test(Paso2Responsable::class, ['escuela' => $this->escuela]);
    }

    public function test_muestra_los_niveles_ya_elegidos_marcados(): void
    {
        $this->conNiveles('preescolar', 'primaria');

        $this->pagina()
            ->assertSet('fase', 'niveles')
            ->assertSet('nivelesSeleccionados', [$this->id('preescolar'), $this->id('primaria')])
            ->assertSeeHtml('wire:target="guardarNiveles"')
            ->assertSee('Guardar y continuar')
            ->assertDontSeeHtml('<x-ui.action-bar');
    }

    public function test_agregar_un_nivel_conserva_los_existentes(): void
    {
        $this->conNiveles('preescolar');
        $preescolar = $this->nivel('preescolar')->id;

        $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar'), $this->id('primaria')])
            ->call('guardarNiveles')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $this->escuela->id]));

        $this->assertDatabaseCount('escuela_niveles', 2);
        $this->assertDatabaseHas('escuela_niveles', ['id' => $preescolar]);
    }

    public function test_quitar_un_nivel_sin_datos_no_pide_confirmacion(): void
    {
        $this->conNiveles('preescolar', 'primaria');

        $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar')])
            ->call('guardarNiveles')
            ->assertSet('porEliminar', [])
            ->assertSessionHas('toast.titulo', 'Niveles guardados')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $this->escuela->id]));

        $this->assertDatabaseMissing('escuela_niveles', ['escuela_id' => $this->escuela->id, 'nivel_educativo_id' => $this->id('primaria')]);
    }

    public function test_quitar_un_nivel_con_datos_pide_confirmacion_y_al_confirmar_lo_borra(): void
    {
        $this->conNiveles('preescolar', 'primaria');
        AulaNivel::create(['escuela_nivel_id' => $this->nivel('primaria')->id, 'numero_aulas' => 6]);

        $pagina = $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar')])
            ->call('guardarNiveles')
            ->assertNoRedirect()
            ->assertSee('¿Quitar niveles con datos capturados?')
            ->assertSee('Aulas del nivel capturadas: 1')
            ->assertSeeHtml('rounded-md bg-error-ink px-lg')
            ->assertSeeHtml('text-on-primary transition')
            ->assertSeeHtml('id="quitar-niveles-titulo" class="text-title-md font-semibold text-error-ink"');

        $this->assertDatabaseHas('escuela_niveles', ['nivel_educativo_id' => $this->id('primaria')]);

        $pagina->call('confirmarQuitarNiveles')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $this->escuela->id]));

        $this->assertDatabaseMissing('escuela_niveles', ['nivel_educativo_id' => $this->id('primaria')]);
        $this->assertDatabaseCount('aulas_nivel', 0);
    }

    public function test_cancelar_no_borra_nada(): void
    {
        $this->conNiveles('preescolar', 'primaria');
        AulaNivel::create(['escuela_nivel_id' => $this->nivel('primaria')->id, 'numero_aulas' => 6]);

        $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar')])
            ->call('guardarNiveles')
            ->call('cancelarQuitarNiveles')
            ->assertSet('porEliminar', [])
            ->assertDontSee('¿Quitar niveles con datos capturados?');

        $this->assertDatabaseCount('escuela_niveles', 2);
    }

    public function test_si_los_datos_crecieron_vuelve_a_pedir_confirmacion(): void
    {
        $this->conNiveles('preescolar', 'primaria');
        $primaria = $this->nivel('primaria')->id;
        AulaNivel::create(['escuela_nivel_id' => $primaria, 'numero_aulas' => 6]);

        $pagina = $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar')])
            ->call('guardarNiveles');
        AulaNivel::create(['escuela_nivel_id' => $primaria, 'numero_aulas' => 2]);

        $pagina->call('confirmarQuitarNiveles')
            ->assertNoRedirect()
            ->assertHasErrors('nivelesSeleccionados')
            ->assertSee('Aulas del nivel capturadas: 2');

        $this->assertDatabaseHas('escuela_niveles', ['id' => $primaria]);
    }

    public function test_desmarcar_otro_nivel_con_datos_tras_abrir_el_dialogo_no_lo_borra(): void
    {
        $this->conNiveles('preescolar', 'primaria', 'secundaria');
        AulaNivel::create(['escuela_nivel_id' => $this->nivel('primaria')->id, 'numero_aulas' => 6]);
        AulaNivel::create(['escuela_nivel_id' => $this->nivel('preescolar')->id, 'numero_aulas' => 3]);

        $pagina = $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar'), $this->id('secundaria')])
            ->call('guardarNiveles')
            ->assertSeeHtml('>Primaria</h3>')
            ->assertDontSeeHtml('>Preescolar</h3>');
        $abiertas = $pagina->get('confirmaciones');

        $pagina->set('nivelesSeleccionados', [$this->id('secundaria')])
            ->call('confirmarQuitarNiveles')
            ->assertNoRedirect()
            ->assertHasErrors('nivelesSeleccionados')
            ->assertSeeHtml('>Primaria</h3>')
            ->assertSeeHtml('>Preescolar</h3>');

        $this->assertGreaterThan($abiertas, $pagina->get('confirmaciones'));
        $this->assertDatabaseHas('escuela_niveles', ['id' => $this->nivel('primaria')->id]);
        $this->assertDatabaseHas('escuela_niveles', ['id' => $this->nivel('preescolar')->id]);
        $this->assertDatabaseCount('aulas_nivel', 2);
    }

    /** Review Focus 3. */
    public function test_confirmar_dos_veces_no_falla(): void
    {
        $this->conNiveles('preescolar', 'primaria');
        AulaNivel::create(['escuela_nivel_id' => $this->nivel('primaria')->id, 'numero_aulas' => 6]);

        $pagina = $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar')])
            ->call('guardarNiveles');
        $pagina->call('confirmarQuitarNiveles');
        $pagina->call('confirmarQuitarNiveles')->assertHasNoErrors();

        $this->assertDatabaseCount('escuela_niveles', 1);
    }

    public function test_quitar_con_paso2_pendiente_esta_permitido(): void
    {
        (new RegistrarResponsableLegal)->ejecutar($this->escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->nivelEn($this->escuela, 'preescolar');
        $this->nivelEn($this->escuela, 'primaria');

        $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar')])
            ->call('guardarNiveles')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $this->escuela->id]));

        $this->assertDatabaseCount('escuela_niveles', 1);
    }

    /** Agregar y quitar a la vez con Paso 2 pendiente: el caso de uso lo rechaza y la página lo explica sin redirigir. */
    public function test_agregar_y_quitar_con_paso2_pendiente_avisa_y_no_cambia_nada(): void
    {
        (new TiposDocumentosSeeder)->run();
        (new RegistrarResponsableLegal)->ejecutar($this->escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->nivelEn($this->escuela, 'preescolar');
        $this->nivelEn($this->escuela, 'primaria');

        $this->pagina()
            ->set('nivelesSeleccionados', [$this->id('preescolar'), $this->id('secundaria')])
            ->call('guardarNiveles')
            ->assertNoRedirect()
            ->assertHasErrors('nivelesSeleccionados')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'Completa el responsable legal y los documentos (Paso 2) antes de seleccionar niveles.');

        $this->assertDatabaseHas('escuela_niveles', ['escuela_id' => $this->escuela->id, 'nivel_educativo_id' => $this->id('primaria')]);
        $this->assertDatabaseMissing('escuela_niveles', ['escuela_id' => $this->escuela->id, 'nivel_educativo_id' => $this->id('secundaria')]);
    }

    public function test_sin_cambios_vuelve_al_resumen_sin_escribir(): void
    {
        $this->conNiveles('preescolar');

        $this->pagina()
            ->call('guardarNiveles')
            ->assertRedirect(route('tramite.resumen', ['escuela' => $this->escuela->id]));

        $this->assertDatabaseCount('escuela_niveles', 1);
    }
}
