<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
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

class Paso2DocumentosVistaTest extends TestCase
{
    use RefreshDatabase;

    private function escuelaConResponsable(): Escuela
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
        (new RegistrarResponsableLegal)->ejecutar($escuela->id, new DatosResponsableLegal(tipoPersona: 'fisica', nombre: 'Juana Pérez'));
        $this->actingAs($solicitante->user);

        return $escuela;
    }

    private function subir(Escuela $escuela, string $clave, ?DatosDocumento $datos = null): void
    {
        app(RegistrarDocumento::class)->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), $datos ?? new DatosDocumento);
    }

    public function test_encabezado_y_filas_pendientes_con_etiqueta_y_estado_de_carga(): void
    {
        $escuela = $this->escuelaConResponsable();

        $this->get(route('tramite.paso2-documentos', ['escuela' => $escuela->id]))
            ->assertOk()
            ->assertSee('Datos generales · Paso 3 de 4')
            ->assertSee('href="'.route('tramite.resumen', ['escuela' => $escuela->id]).'"', false)
            ->assertSee('0 de 6 documentos completos')
            ->assertSee('for="archivos.ine"', false)
            ->assertSee('id="archivos.ine"', false)
            ->assertSee('wire:target="archivos.ine"', false)
            ->assertSee('Subiendo…')
            ->assertSee('Pendiente');
    }

    public function test_una_fila_capturada_ofrece_reemplazar_y_descargar(): void
    {
        $escuela = $this->escuelaConResponsable();
        $this->subir($escuela, 'ine');

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertSee('Completado')
            ->assertSee('Reemplazar')
            ->assertSee('Descargar')
            ->assertSeeHtml('href="'.route('tramite.paso2-documentos.descargar', ['escuela' => $escuela->id, 'clave' => 'ine']).'"')
            ->assertDontSeeHtml('wire:model="archivos.ine"');
    }

    public function test_un_dictamen_vencido_se_marca_en_su_fila_y_en_una_alerta(): void
    {
        $escuela = $this->escuelaConResponsable();
        foreach (['ine', 'acta_nacimiento', 'escritura_inmueble', 'constancia_seguridad_estructural', 'formato_solicitud'] as $clave) {
            $this->subir($escuela, $clave);
        }
        $this->subir($escuela, 'dictamen_uso_suelo', new DatosDocumento(fechaEmision: now()->subDays(60)->toDateString()));

        Livewire::test(Paso2Documentos::class, ['escuela' => $escuela])
            ->assertHasErrors('vigencia')
            ->assertSeeHtml('id="vigencia"')
            ->assertSee('Requiere corrección')
            ->assertDontSee('Revisa los siguientes datos');
    }
}
