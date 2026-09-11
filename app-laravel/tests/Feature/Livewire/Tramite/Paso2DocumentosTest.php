<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\ResponsableLegal\DTO\DatosResponsableLegal;
use App\Application\ResponsableLegal\RegistrarResponsableLegal;
use App\Livewire\Tramite\Paso2Documentos;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Paso2DocumentosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuelaConResponsable(string $tipoPersona = 'fisica'): Escuela
    {
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(
            tipoPersona: $tipoPersona,
            nombre: 'Juana Pérez',
        ));

        return $escuela;
    }

    public function test_arranca_en_la_primera_clave_pendiente(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSet('fase', 'ine');
    }

    public function test_sube_ine_y_avanza_a_la_siguiente_clave(): void
    {
        Storage::fake('documentos');
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivo', UploadedFile::fake()->create('ine.pdf', 50, 'application/pdf'))
            ->call('guardarDocumentoSimple')
            ->assertSet('fase', 'acta_nacimiento')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documentos_escuela', ['escuela_id' => $escuela->id]);
    }

    public function test_rechaza_archivo_que_no_es_pdf(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $this->actingAs($escuela->solicitante->user);

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->set('archivo', UploadedFile::fake()->create('ine.jpg', 50, 'image/jpeg'))
            ->call('guardarDocumentoSimple')
            ->assertHasErrors('archivo');
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $escuela = $this->crearEscuelaConResponsable();
        $otro = Solicitante::factory()->create();
        $this->actingAs($otro->user);

        $response = $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]));

        $response->assertForbidden();
    }
}
