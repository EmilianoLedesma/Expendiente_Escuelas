<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\EscuelaNiveles\RegistrarNivelesSeleccionados;
use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Tramite\Paso2Responsable;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Paso2ResponsableTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaPara(Solicitante $solicitante): Escuela
    {
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_arranca_en_fase_responsable_cuando_no_hay_responsable_legal(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'responsable');
    }

    public function test_resume_en_fase_niveles_cuando_ya_hay_responsable_legal(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSet('fase', 'niveles');
    }

    public function test_redirige_a_paso3_cuando_la_escuela_ya_tiene_niveles(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $preescolar = NivelEducativo::where('clave', 'preescolar')->first();
        (new RegistrarNivelesSeleccionados)->ejecutar($escuela->id, [$preescolar->id]);
        $this->actingAs($solicitante->user);
        $escuelaNivel = EscuelaNivel::where('escuela_id', $escuela->id)->firstOrFail();

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertRedirect(route('tramite.paso3-placeholder', ['escuelaNivel' => $escuelaNivel->id]));
    }

    public function test_tipo_fisica_guarda_y_avanza_a_fase_niveles(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123, Centro')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertSet('fase', 'niveles')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('responsables_legales', ['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
    }

    public function test_captura_domicilio_notificaciones_y_persona_autorizada_recoger(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('domicilioNotificaciones', 'Calle Falsa 123, Centro, Querétaro')
            ->set('personaAutorizadaRecoger', 'María López')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('responsables_legales', [
            'escuela_id' => $escuela->id,
            'domicilio_notificaciones' => 'Calle Falsa 123, Centro, Querétaro',
            'persona_autorizada_recoger' => 'María López',
        ]);
    }

    public function test_domicilio_notificaciones_es_requerido(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->call('guardarResponsable')
            ->assertHasErrors('domicilioNotificaciones');

        $this->assertDatabaseCount('responsables_legales', 0);
    }

    public function test_tipo_moral_captura_todos_los_campos_notariales(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'moral')
            ->set('domicilioNotificaciones', 'Av. Reforma 456')
            ->set('personaMoralForm.razonSocial', 'Colegio Ejemplo A.C.')
            ->set('personaMoralForm.nombreRepresentanteLegal', 'Carlos Ruiz')
            ->set('personaMoralForm.numeroEscrituraConstitutiva', 'E-100')
            ->set('personaMoralForm.fechaEscrituraConstitutiva', '2020-01-15')
            ->set('personaMoralForm.notarioNombre', 'Lic. Pedro Notario')
            ->set('personaMoralForm.notarioNumero', '45')
            ->set('personaMoralForm.notarioCiudad', 'Querétaro')
            ->set('personaMoralForm.folioRegistroPublico', 'F-999')
            ->set('personaMoralForm.fechaInscripcionRpp', '2020-02-01')
            ->call('guardarResponsable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas_morales', [
            'razon_social' => 'Colegio Ejemplo A.C.',
            'nombre_representante_legal' => 'Carlos Ruiz',
            'numero_escritura_constitutiva' => 'E-100',
            'fecha_escritura_constitutiva' => '2020-01-15',
            'notario_nombre' => 'Lic. Pedro Notario',
            'notario_numero' => '45',
            'notario_ciudad' => 'Querétaro',
            'folio_registro_publico' => 'F-999',
            'fecha_inscripcion_rpp' => '2020-02-01',
        ]);
    }

    public function test_tipo_fisica_con_gestor_captura_fecha_nacimiento_y_datos_notariales_del_gestor(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('tipoPersona', 'fisica_con_gestor')
            ->set('domicilioNotificaciones', 'Calle Falsa 123')
            ->set('personaFisicaForm.nombre', 'Juana Pérez')
            ->set('personaFisicaForm.fechaNacimiento', '1985-06-20')
            ->set('gestorForm.nombre', 'Roberto Gómez')
            ->set('gestorForm.numeroPoder', 'P-500')
            ->set('gestorForm.notarioNombre', 'Lic. Ana Notaria')
            ->set('gestorForm.notarioNumero', '10')
            ->set('gestorForm.fechaPoder', '2021-03-10')
            ->call('guardarResponsable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('personas_fisicas', [
            'nombre' => 'Juana Pérez',
            'fecha_nacimiento' => '1985-06-20',
        ]);
        $this->assertDatabaseHas('gestores', [
            'nombre' => 'Roberto Gómez',
            'numero_poder' => 'P-500',
            'notario_nombre' => 'Lic. Ana Notaria',
            'notario_numero' => '10',
            'fecha_poder' => '2021-03-10',
        ]);
    }

    public function test_rechaza_envio_sin_ningun_nivel_seleccionado(): void
    {
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->actingAs($solicitante->user);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('nivelesSeleccionados', [])
            ->call('guardarNiveles')
            ->assertHasErrors('nivelesSeleccionados');

        $this->assertDatabaseCount('escuela_niveles', 0);
    }

    public function test_envio_con_niveles_crea_escuela_niveles_y_redirige(): void
    {
        (new CatalogoMinimoSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $escuela = $this->crearEscuelaPara($solicitante);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->actingAs($solicitante->user);
        $preescolar = NivelEducativo::where('clave', 'preescolar')->first();

        $component = Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->set('nivelesSeleccionados', [$preescolar->id])
            ->call('guardarNiveles');

        $this->assertDatabaseHas('escuela_niveles', ['escuela_id' => $escuela->id, 'nivel_educativo_id' => $preescolar->id]);
        $escuelaNivel = EscuelaNivel::where('escuela_id', $escuela->id)->firstOrFail();
        $component->assertRedirect(route('tramite.paso3-placeholder', ['escuelaNivel' => $escuelaNivel->id]));
    }
}
