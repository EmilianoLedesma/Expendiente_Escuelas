<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DocumentosCompletos;
use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\RegistrarDocumento;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentosCompletosTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscuela(): Escuela
    {
        $solicitante = Solicitante::factory()->create();
        $plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);

        return Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => $solicitante->id]);
    }

    public function test_claves_aplicables_para_fisica_incluye_las_de_ambas_y_fisica(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = (new DocumentosCompletos)->clavesAplicables('fisica');

        $this->assertContains('acta_nacimiento', $claves);
        $this->assertNotContains('escritura_poder_facultades', $claves);
        $this->assertNotContains('acta_constitutiva', $claves);
        $this->assertNotContains('poder_gestor', $claves);
        // 13 filas totales: 10 son aplica_persona='ambas' (aplican siempre),
        // 2 son 'moral' (no aplican a fisica), 1 es 'fisica_con_gestor' (no
        // aplica a fisica). 10 + 0 + 0 = 10.
        $this->assertCount(10, $claves);
    }

    public function test_claves_aplicables_para_moral_incluye_acta_constitutiva_y_escritura_poder(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = (new DocumentosCompletos)->clavesAplicables('moral');

        $this->assertContains('acta_constitutiva', $claves);
        $this->assertContains('escritura_poder_facultades', $claves);
        $this->assertContains('acta_nacimiento', $claves);
        $this->assertNotContains('poder_gestor', $claves);
        // 10 'ambas' + 2 'moral' propias (escritura_poder_facultades, acta_constitutiva) = 12.
        $this->assertCount(12, $claves);
    }

    public function test_claves_aplicables_para_fisica_con_gestor_incluye_poder_gestor(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = (new DocumentosCompletos)->clavesAplicables('fisica_con_gestor');

        $this->assertContains('poder_gestor', $claves);
        $this->assertNotContains('escritura_poder_facultades', $claves);
        $this->assertNotContains('acta_constitutiva', $claves);
        // 10 'ambas' + 1 'fisica_con_gestor' propia (poder_gestor) = 11.
        $this->assertCount(11, $claves);
    }

    public function test_el_orden_de_los_7_documentos_originales_no_cambia(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = (new DocumentosCompletos)->clavesAplicables('fisica');
        $primerosSiete = array_slice($claves, 0, 6);

        $this->assertSame(
            ['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'formato_solicitud'],
            $primerosSiete,
        );
    }

    public function test_para_escuela_falso_sin_ningun_documento(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->assertFalse((new DocumentosCompletos)->paraEscuela($escuela->id, 'fisica'));
        $this->assertCount(10, (new DocumentosCompletos)->clavesPendientes($escuela->id, 'fisica'));
    }

    public function test_para_escuela_verdadero_cuando_los_10_estan_registrados(): void
    {
        Storage::fake('documentos');
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();
        // WS-2.4b: RegistrarDocumento ahora exige responsable legal capturado antes de escribir.
        ResponsableLegal::create(['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
        $registrar = new RegistrarDocumento;

        foreach ((new DocumentosCompletos)->clavesAplicables('fisica') as $clave) {
            $registrar->ejecutar($escuela->id, $clave, UploadedFile::fake()->create("{$clave}.pdf", 10, 'application/pdf'), new DatosDocumento);
        }

        $this->assertTrue((new DocumentosCompletos)->paraEscuela($escuela->id, 'fisica'));
        $this->assertSame([], (new DocumentosCompletos)->clavesPendientes($escuela->id, 'fisica'));
    }

    /**
     * WS-5a: clavesAplicables() ahora deriva de tipos_documentos, así que ya
     * no puede devolver una clave ausente del catálogo (antes era un array
     * fijo que sí podía desincronizarse de un catálogo vacío/parcial). Con
     * catálogo vacío, clavesAplicables() devuelve [] y clavesPendientes() no
     * tiene nada que revisar — ya no hay "Undefined array key" que evitar.
     */
    public function test_catalogo_vacio_no_produce_pendientes_ni_error(): void
    {
        // Deliberadamente sin TiposDocumentosSeeder: catálogo vacío.
        $escuela = $this->crearEscuela();

        $this->assertSame([], (new DocumentosCompletos)->clavesPendientes($escuela->id, 'fisica'));
    }
}
