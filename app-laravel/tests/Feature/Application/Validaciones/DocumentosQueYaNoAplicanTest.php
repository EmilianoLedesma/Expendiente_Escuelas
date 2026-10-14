<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Validaciones\ConstruirContextoValidacion;
use App\Domain\Validaciones\Documental\CatalogoReglasDocumentales;
use App\Domain\Validaciones\Documental\Hecho;
use App\Models\ResponsableLegal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/**
 * WS-7b-1 U4: un documento guardado que dejó de aplicar (cambio de tipo de persona) no
 * aporta hechos a la validación final. Hoy ningún documento con datos es de un solo tipo,
 * así que el test vuelve "solo moral" a la constancia de situación fiscal.
 */
class DocumentosQueYaNoAplicanTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    public function test_un_documento_con_datos_que_ya_no_aplica_no_aporta_hechos(): void
    {
        $escuela = $this->crearEscuelaConPlantel();
        DB::table('tipos_documentos')->where('clave', 'constancia_situacion_fiscal')->update(['aplica_persona' => 'moral']);
        $this->registrarResponsable($escuela, 'moral');
        $this->subirDocumento($escuela, 'constancia_situacion_fiscal', $this->datosConsistentes('constancia_situacion_fiscal', 'moral'));
        $fuentes = fn (): array => array_map(
            fn (Hecho $hecho) => $hecho->documentoClave,
            app(ConstruirContextoValidacion::class)->ejecutar($escuela->id)->hechos,
        );

        // Control: mientras aplica, sus datos sí se leen.
        $this->assertContains(CatalogoReglasDocumentales::FUENTE_FISCAL, $fuentes());

        ResponsableLegal::where('escuela_id', $escuela->id)->update(['tipo_persona' => 'fisica']);

        $this->assertNotContains(CatalogoReglasDocumentales::FUENTE_FISCAL, $fuentes());
    }
}
