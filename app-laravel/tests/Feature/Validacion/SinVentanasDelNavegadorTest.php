<?php

namespace Tests\Feature\Validacion;

use App\Livewire\Tramite\Paso1Preregistro;
use App\Livewire\Tramite\Paso2Responsable;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un <input type="email"> o type="number" dentro de un <form> sin
 * novalidate hace que el navegador muestre su propia ventana emergente (en
 * el idioma del navegador) antes de que Livewire reciba el envío. Los
 * mensajes deben ser los del servidor, en español y debajo del campo.
 */
class SinVentanasDelNavegadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_todo_formulario_livewire_desactiva_la_validacion_nativa(): void
    {
        $sinNovalidate = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($archivo) => str_ends_with($archivo->getFilename(), '.blade.php'))
            ->flatMap(function ($archivo) {
                preg_match_all('/<form\b[^>]*wire:submit[^>]*>/s', $archivo->getContents(), $formularios);

                return collect($formularios[0])
                    ->reject(fn (string $etiqueta) => str_contains($etiqueta, 'novalidate'))
                    ->map(fn () => $archivo->getRelativePathname());
            })
            ->values()
            ->all();

        $this->assertSame([], $sinNovalidate);
    }

    public function test_los_campos_de_formato_fijo_limitan_lo_que_se_puede_escribir(): void
    {
        $solicitante = Solicitante::factory()->create();
        $this->actingAs($solicitante->user);

        Livewire::test(Paso1Preregistro::class)
            ->assertSeeHtmlInOrder(['id="codigoPostal"', 'maxlength="5"'])
            ->assertSeeHtmlInOrder(['id="telefono"', 'maxlength="20"'])
            ->assertSeeHtmlInOrder(['id="calle"', 'maxlength="150"']);

        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);

        Livewire::test(Paso2Responsable::class, ['escuela' => $escuela])
            ->assertSeeHtmlInOrder(['id="personaFisicaForm.curp"', 'maxlength="18"'])
            ->assertSeeHtmlInOrder(['id="personaFisicaForm.rfc"', 'maxlength="13"'])
            ->assertSeeHtmlInOrder(['id="personaFisicaForm.nombre"', 'maxlength="200"']);
    }

    public function test_los_campos_de_texto_se_validan_al_salir_de_ellos(): void
    {
        $sinBlur = collect(File::allFiles(resource_path('views/livewire/tramite')))
            ->flatMap(function ($archivo) {
                preg_match_all('/<x-ui\.(?:input|textarea)\b[^>]*\bwire:model="[^"]+"/', $archivo->getContents(), $campos);

                return array_map(fn () => $archivo->getRelativePathname(), $campos[0]);
            })
            ->values()
            ->all();

        $this->assertSame([], $sinBlur, 'Campos con wire:model diferido: el mensaje solo aparecería al enviar.');
    }
}
