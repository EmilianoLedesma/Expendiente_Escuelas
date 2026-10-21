<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Inmueble\RegistrarDatosInmueble;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Tramite\Paso3\DatosInmueble;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ResponsableNivel;
use App\Models\ServicioCercano;
use App\Models\Solicitante;
use App\Models\TernaNombre;
use App\Models\User;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\TestCase;

class Paso3DatosInmuebleTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();

        $this->solicitante = Solicitante::factory()->create();
        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->completarPaso2($this->escuela->id);
    }

    private function escuelaNivel(string $claveNivel = 'primaria'): EscuelaNivel
    {
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        $this->completarPaso24($escuelaNivel->id);

        return $escuelaNivel;
    }

    public function test_la_ruta_de_paso3_ahora_muestra_el_sub_paso_1(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertOk();
        $response->assertSee('Datos del inmueble', false);
    }

    // WS-2.6 — PRD §5 Paso 3.1: el domicilio del plantel (capturado en Paso 1)
    // se muestra siempre, de solo lectura, nunca como input editable.
    public function test_muestra_el_domicilio_del_plantel_en_solo_lectura(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertSee('Calle 1', false);
        $response->assertSee('Centro', false);
        $response->assertSee('Querétaro', false);
        $response->assertSee('76000', false);

        // Nunca un input editable sobre el domicilio.
        $response->assertDontSee('id="calle"', false);
        $response->assertDontSee('id="colonia"', false);
        $response->assertDontSee('id="municipio"', false);
        $response->assertDontSee('id="codigoPostal"', false);
        $response->assertDontSee('wire:model.blur="calle"', false);
        $response->assertDontSee('wire:model.blur="colonia"', false);
        $response->assertDontSee('wire:model.blur="municipio"', false);
        $response->assertDontSee('wire:model.blur="codigoPostal"', false);
    }

    public function test_muestra_la_terna_de_nombres_ya_capturada_en_solo_lectura(): void
    {
        $escuelaNivel = $this->escuelaNivel();
        TernaNombre::create(['escuela_id' => $this->escuela->id, 'numero_propuesta' => 1, 'nombre_propuesto' => 'Colegio Alfa']);

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertSee('Colegio Alfa', false);
    }

    public function test_guardar_persiste_y_redirige_a_infraestructura(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', 1200.5)
            ->set('metrosConstruidos', 640)
            ->set('tieneAstaBandera', true)
            ->call('guardar')
            ->assertSessionHas('toast.titulo', 'Datos del inmueble guardados')
            ->assertRedirect(route('tramite.paso3-infraestructura', ['escuelaNivel' => $escuelaNivel->id]));

        $this->assertEquals(1200.5, (float) $this->plantel->fresh()->metros_totales);
    }

    public function test_la_etiqueta_de_metros_construidos_ya_no_dice_opcional(): void
    {
        $html = Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $this->escuelaNivel()])
            ->html();

        preg_match('/<label for="metrosConstruidos".*?<\/label>/s', $html, $etiqueta);
        $this->assertStringContainsString('Superficie construida (m²)', $etiqueta[0] ?? '');
        $this->assertStringNotContainsString('(opcional)', $etiqueta[0]);
    }

    public function test_los_metros_construidos_son_obligatorios(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        $prueba = Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', 1200)
            ->set('metrosConstruidos', '')
            ->call('guardar')
            ->assertHasErrors(['metrosConstruidos' => 'required']);

        $this->assertSame('El campo superficie construida es obligatorio.', $prueba->errors()->first('metrosConstruidos'));
        $this->assertNull($this->plantel->fresh()->metros_totales);
    }

    public function test_metros_totales_es_obligatorio(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', '')
            ->call('guardar')
            ->assertHasErrors('metrosTotales');

        $this->assertNull($this->plantel->fresh()->metros_totales);
    }

    public function test_guarda_los_servicios_cercanos_agregados(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', 900)
            ->set('metrosConstruidos', 600)
            ->call('agregarServicio')
            ->set('serviciosCercanos.0.nombre', 'Cruz Roja')
            ->set('serviciosCercanos.0.tipo', 'emergencia')
            ->set('serviciosCercanos.0.distanciaValor', 1.5)
            ->set('serviciosCercanos.0.distanciaUnidad', 'km')
            ->call('guardar');

        $this->assertDatabaseHas('servicios_cercanos', [
            'plantel_id' => $this->plantel->id,
            'nombre' => 'Cruz Roja',
            'tipo' => 'emergencia',
        ]);
    }

    public function test_guarda_un_estudio_actual_con_la_variante_otro(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', 900)
            ->set('metrosConstruidos', 600)
            ->call('agregarEstudio')
            ->set('estudiosActuales.0.nivelEducativoId', '')
            ->set('estudiosActuales.0.otroNivelTexto', 'Academia de inglés')
            ->set('estudiosActuales.0.numeroAlumnos', 30)
            ->call('guardar');

        $this->assertDatabaseHas('inmueble_estudios_actuales', [
            'plantel_id' => $this->plantel->id,
            'nivel_educativo_id' => null,
            'otro_nivel_texto' => 'Academia de inglés',
            'numero_alumnos' => 30,
        ]);
    }

    public function test_un_segundo_nivel_del_mismo_plantel_se_auto_completa_y_avanza(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $primaria])
            ->set('metrosTotales', 900)
            ->set('metrosConstruidos', 600)
            ->call('guardar');

        $preescolar = $this->escuelaNivel('preescolar');

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $preescolar->id]));

        $response->assertRedirect(route('tramite.paso3-infraestructura', ['escuelaNivel' => $preescolar->id]));

        $pasoId = DB::table('pasos_captura')->where('clave', 'inmueble')->value('id');
        $this->assertDatabaseHas('escuela_nivel_pasos', [
            'escuela_nivel_id' => $preescolar->id,
            'paso_captura_id' => $pasoId,
            'estado' => 'completado',
        ]);
    }

    private function capturarComoDueno(EscuelaNivel $nivel): void
    {
        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->set('metrosTotales', 900)
            ->set('metrosConstruidos', 600)
            ->set('colindanciaNorte', 'Calle Norte')
            ->call('agregarServicio')
            ->set('serviciosCercanos.0.nombre', 'Cruz Roja')
            ->call('agregarEstudio')
            ->set('estudiosActuales.0.otroNivelTexto', 'Academia')
            ->set('estudiosActuales.0.numeroAlumnos', 30)
            ->call('guardar');
    }

    // WS-7b-2: el dueño que vuelve a un paso ya completado edita, no rebota.
    public function test_el_dueno_que_vuelve_ve_el_formulario_precargado(): void
    {
        $nivel = $this->escuelaNivel();
        $this->capturarComoDueno($nivel);

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->assertNoRedirect()
            ->assertSet('soloLectura', false)
            ->assertSet('metrosTotales', '900.00')
            ->assertSet('metrosConstruidos', '600.00')
            ->assertSet('colindanciaNorte', 'Calle Norte')
            ->assertSet('serviciosCercanos.0.nombre', 'Cruz Roja')
            ->assertSet('estudiosActuales.0.otroNivelTexto', 'Academia')
            ->assertSet('estudiosActuales.0.numeroAlumnos', 30)
            ->assertDontSee('Otro nivel ya capturó');
    }

    public function test_muestra_el_aviso_de_bloqueo_cuando_otro_nivel_capturo(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $this->capturarComoDueno($primaria);
        $preescolar = $this->escuelaNivel('preescolar');
        $preescolar->update(['modalidad' => 'escolarizada']);

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $primaria])
            ->assertNoRedirect()
            ->assertSee('Otro nivel ya capturó: solo puedes completar campos vacíos');
    }

    private function bloqueadoConDatos(): EscuelaNivel
    {
        $primaria = $this->escuelaNivel('primaria');
        $this->capturarComoDueno($primaria);
        $this->escuelaNivel('preescolar')->update(['modalidad' => 'escolarizada']);

        return $primaria;
    }

    private function etiquetaInput(string $html, string $id): string
    {
        preg_match('/<(?:input|select)[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>/s', $html, $m);
        $this->assertNotEmpty($m, "No se encontró el control {$id}");

        return $m[0];
    }

    public function test_bloqueado_deshabilita_los_campos_con_valor_y_deja_editables_los_nulos(): void
    {
        $html = Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $this->bloqueadoConDatos()])
            ->html();

        $this->assertStringContainsString('disabled', $this->etiquetaInput($html, 'metrosTotales'));
        $this->assertStringContainsString('disabled', $this->etiquetaInput($html, 'colindanciaNorte'));
        $this->assertStringContainsString('disabled', $this->etiquetaInput($html, 'tieneAstaBandera'));
        $this->assertStringNotContainsString('disabled', $this->etiquetaInput($html, 'colindanciaSur'));
        $this->assertStringNotContainsString('disabled', $this->etiquetaInput($html, 'latitud'));
        $this->assertStringNotContainsString('disabled', $this->etiquetaInput($html, 'areaCivicaM2'));
        // Conjuntos ya capturados: solo lectura, sin agregar ni quitar.
        $this->assertStringContainsString('disabled', $this->etiquetaInput($html, 'serviciosCercanos.0.nombre'));
        $this->assertStringNotContainsString('Agregar institución', $html);
        $this->assertStringNotContainsString('Quitar', $html);
        $this->assertStringNotContainsString('Agregar estudio', $html);
    }

    public function test_desbloqueado_todos_los_campos_siguen_editables(): void
    {
        $nivel = $this->escuelaNivel();
        $this->capturarComoDueno($nivel);

        $html = Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->html();

        $this->assertStringNotContainsString('disabled', $this->etiquetaInput($html, 'metrosTotales'));
        $this->assertStringNotContainsString('disabled', $this->etiquetaInput($html, 'serviciosCercanos.0.nombre'));
        $this->assertStringContainsString('Agregar institución', $html);
        $this->assertStringContainsString('Quitar', $html);
    }

    public function test_bloqueado_un_valor_alterado_en_un_campo_con_valor_no_se_guarda(): void
    {
        $primaria = $this->bloqueadoConDatos();

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $primaria])
            ->set('metrosTotales', 5000)
            ->set('colindanciaNorte', 'Alterada')
            ->set('colindanciaSur', 'Calle Sur')
            ->set('serviciosCercanos.0.nombre', 'Alterado')
            ->call('guardar');

        $plantel = $this->plantel->fresh();
        $this->assertEquals(900, (float) $plantel->metros_totales);
        $this->assertSame('Calle Norte', $plantel->colindancia_norte);
        $this->assertSame('Calle Sur', $plantel->colindancia_sur);
        $this->assertSame(['Cruz Roja'], ServicioCercano::where('plantel_id', $plantel->id)->pluck('nombre')->all());
    }

    public function test_guardar_edicion_actualiza_y_vuelve_a_infraestructura(): void
    {
        $nivel = $this->escuelaNivel();
        $this->capturarComoDueno($nivel);

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->set('metrosTotales', 1000)
            ->set('serviciosCercanos.0.nombre', 'Hospital General')
            ->call('guardar')
            ->assertRedirect(route('tramite.paso3-infraestructura', ['escuelaNivel' => $nivel->id]));

        $this->assertEquals(1000, (float) $this->plantel->fresh()->metros_totales);
        $this->assertSame(['Hospital General'], ServicioCercano::where('plantel_id', $this->plantel->id)->pluck('nombre')->all());
    }

    public function test_tramite_hermano_enviado_muestra_el_error_de_plantel(): void
    {
        $nivel = $this->escuelaNivel();
        $this->capturarComoDueno($nivel);

        $this->mock(RegistrarDatosInmueble::class, function ($mock) {
            $mock->shouldReceive('ejecutar')
                ->andThrow(new PrecondicionIncumplida(TramiteEditable::ENVIADO, 'Un trámite de este plantel ya se envió a SEDEQ.'));
        });

        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $nivel])
            ->call('guardar')
            ->assertNoRedirect()
            ->assertHasErrors('plantel');
    }

    public function test_un_responsable_por_nivel_sigue_siendo_solo_lectura_y_redirige(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $this->capturarComoDueno($primaria);
        $preescolar = $this->escuelaNivel('preescolar');
        $delegado = User::factory()->create();
        ResponsableNivel::create(['user_id' => $delegado->id, 'escuela_nivel_id' => $preescolar->id, 'invitado_por_solicitante_id' => $this->solicitante->id]);

        Livewire::actingAs($delegado)
            ->test(DatosInmueble::class, ['escuelaNivel' => $preescolar])
            ->assertRedirect(route('tramite.paso3-infraestructura', ['escuelaNivel' => $preescolar->id]));
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $escuelaNivel = $this->escuelaNivel();
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)
            ->get(route('tramite.paso3-inmueble', ['escuelaNivel' => $escuelaNivel->id]));

        $response->assertForbidden();
    }

    // WS-2.4a — un DatosInvalidos lanzado por el caso de uso debe convertirse
    // en errores de campo, no en un 500.
    public function test_datos_invalidos_del_caso_de_uso_se_convierte_en_errores_de_campo(): void
    {
        $escuelaNivel = $this->escuelaNivel();

        $this->mock(RegistrarDatosInmueble::class, function ($mock) {
            $mock->shouldReceive('ejecutar')->andThrow(new DatosInvalidos([
                'latitud' => 'La latitud debe estar entre -90 y 90.',
            ]));
        });

        // latitud (con su longitud: van en par) dentro de rango para que la validación de Livewire (que ya
        // la cubre client-side) no intercepte antes de llegar al caso de uso
        // mockeado — así se prueba específicamente el catch de DatosInvalidos.
        Livewire::actingAs($this->solicitante->user)
            ->test(DatosInmueble::class, ['escuelaNivel' => $escuelaNivel])
            ->set('metrosTotales', 900)
            ->set('metrosConstruidos', 600)
            ->set('latitud', 20.0)
            ->set('longitud', -100.0)
            ->call('guardar')
            ->assertHasErrors('latitud');

        $this->assertNull($this->plantel->fresh()->metros_totales);
    }
}
