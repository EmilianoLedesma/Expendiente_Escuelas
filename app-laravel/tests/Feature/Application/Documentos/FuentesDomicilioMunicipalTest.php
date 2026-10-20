<?php

namespace Tests\Feature\Application\Documentos;

use App\Application\Documentos\DTO\DatosDocumento;
use App\Application\Documentos\FuentesDomicilioMunicipal;
use App\Application\Documentos\RegistrarDocumento;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\ResponsableLegal;
use App\Models\Solicitante;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\DictamenDeEjemplo;
use Tests\TestCase;

/**
 * Matriz de disponibilidad de domicilios estructurados (spec docs/specs/2026-10-20-paso2-integracion-end-to-end.md §6.1):
 * hoy ningún documento municipal, aparte del propio certificado, guarda un domicilio. El servicio lo dice de forma
 * explícita en lugar de fingir evidencia: no lee PDF, no usa OCR y excluye el certificado por construcción.
 */
class FuentesDomicilioMunicipalTest extends TestCase
{
    use CompletaPaso2;
    use RefreshDatabase;

    public function test_la_matriz_declara_que_ningun_documento_municipal_trae_domicilio_estructurado(): void
    {
        $matriz = app(FuentesDomicilioMunicipal::class)->disponibilidad();

        foreach (['dictamen_uso_suelo', 'visto_bueno_proteccion_civil', 'constancia_seguridad_estructural', 'escritura_inmueble'] as $clave) {
            $this->assertArrayHasKey($clave, $matriz);
            $this->assertFalse($matriz[$clave]['domicilio_estructurado'], $clave);
        }

        $this->assertTrue($matriz['certificado_numero_oficial']['domicilio_estructurado']);
        $this->assertTrue($matriz['certificado_numero_oficial']['excluido'], 'el certificado no puede probar su propia exención');
    }

    public function test_con_todos_los_documentos_municipales_capturados_no_hay_fuentes(): void
    {
        (new TiposDocumentosSeeder)->run();
        $plantel = Plantel::create(['calle' => 'Avenida Hidalgo', 'numero_ext' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $escuela = Escuela::create(['plantel_id' => $plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
        ResponsableLegal::create(['escuela_id' => $escuela->id, 'tipo_persona' => 'fisica']);
        $this->elegirNivel($escuela->id);
        $this->completarPaso2($escuela->id);
        app(RegistrarDocumento::class)->ejecutar($escuela->id, 'dictamen_uso_suelo', UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), DictamenDeEjemplo::conVigencia('sin_fecha_expresa'));
        app(RegistrarDocumento::class)->ejecutar($escuela->id, 'certificado_numero_oficial', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), new DatosDocumento(
            domicilioCalle: 'Avenida Hidalgo', domicilioNumeroExt: '12', domicilioColonia: 'Centro', domicilioMunicipio: 'Querétaro', domicilioCodigoPostal: '76000',
        ));

        $this->assertSame([], app(FuentesDomicilioMunicipal::class)->para($plantel->id));
    }
}
