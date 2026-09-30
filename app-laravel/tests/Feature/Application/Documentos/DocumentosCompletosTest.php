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
use RuntimeException;
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
        // 11 filas de Paso 2.2 (las 5 escuela_nivel de Paso 2.4 no cuentan): 8 'ambas',
        // 2 'moral', 1 'fisica_con_gestor'. fisica = 8.
        $this->assertCount(8, $claves);
        $this->assertNotContains('formato_solicitud', $claves);
    }

    public function test_claves_aplicables_para_moral_incluye_acta_constitutiva_y_escritura_poder(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = (new DocumentosCompletos)->clavesAplicables('moral');

        $this->assertContains('acta_constitutiva', $claves);
        $this->assertContains('escritura_poder_facultades', $claves);
        $this->assertContains('acta_nacimiento', $claves);
        $this->assertNotContains('poder_gestor', $claves);
        // 8 'ambas' + 2 'moral' propias = 10.
        $this->assertCount(10, $claves);
    }

    public function test_claves_aplicables_para_fisica_con_gestor_incluye_poder_gestor(): void
    {
        (new TiposDocumentosSeeder)->run();

        $claves = (new DocumentosCompletos)->clavesAplicables('fisica_con_gestor');

        $this->assertContains('poder_gestor', $claves);
        $this->assertNotContains('escritura_poder_facultades', $claves);
        $this->assertNotContains('acta_constitutiva', $claves);
        // 8 'ambas' + 1 'fisica_con_gestor' (poder_gestor) = 9.
        $this->assertCount(9, $claves);
    }

    public function test_el_orden_del_checklist_sigue_el_orden_del_seeder(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->assertSame(
            ['ine', 'acta_nacimiento', 'escritura_inmueble', 'dictamen_uso_suelo', 'constancia_seguridad_estructural', 'visto_bueno_proteccion_civil', 'plano_inmueble', 'certificado_numero_oficial'],
            (new DocumentosCompletos)->clavesAplicables('fisica'),
        );
    }

    public function test_no_incluye_documentos_por_nivel_ni_el_recibo_del_plantel(): void
    {
        (new TiposDocumentosSeeder)->run();

        foreach (['fisica', 'moral', 'fisica_con_gestor'] as $tipoPersona) {
            $claves = (new DocumentosCompletos)->clavesAplicables($tipoPersona);
            foreach (['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_primaria', 'inventario_laboratorio', 'recibo_pago_derechos_plantel'] as $clave) {
                $this->assertNotContains($clave, $claves, "{$clave} no debe aplicar en Paso 2.2 ({$tipoPersona})");
            }
        }
    }

    public function test_para_escuela_falso_sin_ningun_documento(): void
    {
        (new TiposDocumentosSeeder)->run();
        $escuela = $this->crearEscuela();

        $this->assertFalse((new DocumentosCompletos)->paraEscuela($escuela->id, 'fisica'));
        $this->assertCount(8, (new DocumentosCompletos)->clavesPendientes($escuela->id, 'fisica'));
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
     * WS-5a: clavesAplicables() deriva de tipos_documentos. Un catálogo vacío
     * (seeder sin correr) no debe dejar pasar la compuerta de Paso 2 con
     * "cero documentos aplicables = completo": falla con un diagnóstico.
     */
    public function test_catalogo_vacio_lanza_runtime_exception_con_diagnostico(): void
    {
        // Deliberadamente sin TiposDocumentosSeeder: catálogo vacío.
        $escuela = $this->crearEscuela();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TiposDocumentosSeeder');

        (new DocumentosCompletos)->paraEscuela($escuela->id, 'fisica');
    }
}
