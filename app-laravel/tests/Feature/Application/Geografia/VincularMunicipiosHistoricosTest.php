<?php

namespace Tests\Feature\Application\Geografia;

use App\Application\Geografia\VincularMunicipiosHistoricos;
use App\Models\Plantel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\ConCatalogoGeografico;
use Tests\TestCase;

/**
 * Vinculación de municipios históricos en texto al catálogo (spec 2026-10-20 §8): solo coincidencias inequívocas,
 * en simulación por omisión, sin sobrescribir el texto ni un id existente, con las ambigüedades y los textos sin
 * correspondencia reportados. No se asume que todo municipio es de Querétaro fuera de los planteles (ADR-008).
 */
class VincularMunicipiosHistoricosTest extends TestCase
{
    use ConCatalogoGeografico;
    use RefreshDatabase;

    private function plantel(string $municipio, ?int $municipioId = null): Plantel
    {
        return Plantel::create(['calle' => 'Calle '.uniqid(), 'colonia' => 'Centro', 'municipio' => $municipio, 'municipio_id' => $municipioId, 'codigo_postal' => '76000']);
    }

    private function certificado(string $municipio, ?int $entidadId = null): int
    {
        $plantel = $this->plantel('Querétaro');
        $tipo = DB::table('tipos_documentos')->where('clave', 'certificado_numero_oficial')->value('id')
            ?? DB::table('tipos_documentos')->insertGetId(['clave' => 'certificado_numero_oficial', 'nombre' => 'Certificado', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'created_at' => now(), 'updated_at' => now()]);
        $documento = DB::table('documentos_plantel')->insertGetId(['plantel_id' => $plantel->id, 'tipo_documento_id' => $tipo, 'archivo_path' => 'c.pdf']);
        DB::table('certificados_numero_oficial')->insert(['documento_plantel_id' => $documento, 'calle' => 'Calle', 'colonia' => 'Centro', 'municipio' => $municipio, 'codigo_postal' => '76000', 'entidad_federativa_id' => $entidadId]);

        return $documento;
    }

    public function test_sin_catalogo_importado_no_hace_nada_y_lo_dice(): void
    {
        $this->plantel('Querétaro');

        $this->expectException(RuntimeException::class);
        app(VincularMunicipiosHistoricos::class)->ejecutar(aplicar: true);
    }

    public function test_la_simulacion_reporta_sin_escribir(): void
    {
        $this->sembrarCatalogoGeografico();
        $exacto = $this->plantel('Querétaro');
        $sinCorrespondencia = $this->plantel('Qro.');

        $reporte = app(VincularMunicipiosHistoricos::class)->ejecutar(aplicar: false);

        $this->assertSame([$exacto->id], array_column($reporte['planteles']['vinculables'], 'id'));
        $this->assertSame([$sinCorrespondencia->id], array_column($reporte['planteles']['sin_correspondencia'], 'id'));
        $this->assertNull($exacto->fresh()->municipio_id);
    }

    public function test_aplicar_vincula_solo_lo_inequivoco_y_conserva_el_texto(): void
    {
        $this->sembrarCatalogoGeografico();
        $acentos = $this->plantel('  san juan del rio ');
        $abreviado = $this->plantel('SJR');
        $yaVinculado = $this->plantel('Texto viejo', $this->municipio('CORREGIDORA')->id);

        app(VincularMunicipiosHistoricos::class)->ejecutar(aplicar: true);

        $this->assertSame($this->municipio('SAN JUAN DEL RÍO')->id, $acentos->fresh()->municipio_id);
        $this->assertSame('  san juan del rio ', $acentos->fresh()->municipio, 'el texto original no se sobrescribe');
        $this->assertNull($abreviado->fresh()->municipio_id);
        $this->assertSame($this->municipio('CORREGIDORA')->id, $yaVinculado->fresh()->municipio_id, 'un id existente no se toca');
    }

    public function test_un_plantel_solo_se_vincula_a_queretaro_aunque_otra_entidad_tenga_el_mismo_nombre(): void
    {
        $this->sembrarCatalogoGeografico();
        $plantel = $this->plantel('Corregidora');

        app(VincularMunicipiosHistoricos::class)->ejecutar(aplicar: true);

        $this->assertSame($this->municipio('CORREGIDORA')->id, $plantel->fresh()->municipio_id);
    }

    public function test_un_certificado_sin_entidad_es_ambiguo_si_el_nombre_existe_en_varias_entidades(): void
    {
        $this->sembrarCatalogoGeografico();
        $ambiguo = $this->certificado('Corregidora');
        $conEntidad = $this->certificado('Celaya', $this->guanajuato->id);

        $reporte = app(VincularMunicipiosHistoricos::class)->ejecutar(aplicar: true);

        $this->assertSame([$ambiguo], array_column($reporte['certificados']['ambiguos'], 'id'));
        $this->assertNull(DB::table('certificados_numero_oficial')->where('documento_plantel_id', $ambiguo)->value('municipio_id'));
        $this->assertSame($this->municipio('CELAYA', $this->guanajuato)->id, (int) DB::table('certificados_numero_oficial')->where('documento_plantel_id', $conEntidad)->value('municipio_id'));
    }

    public function test_repetirlo_es_idempotente(): void
    {
        $this->sembrarCatalogoGeografico();
        $this->plantel('Querétaro');

        app(VincularMunicipiosHistoricos::class)->ejecutar(aplicar: true);
        $segunda = app(VincularMunicipiosHistoricos::class)->ejecutar(aplicar: true);

        $this->assertSame([], $segunda['planteles']['vinculables']);
    }

    public function test_el_comando_simula_por_omision(): void
    {
        $this->sembrarCatalogoGeografico();
        $plantel = $this->plantel('Querétaro');

        $this->artisan('geografia:vincular-municipios')->expectsOutputToContain('Simulación')->assertSuccessful();
        $this->assertNull($plantel->fresh()->municipio_id);

        $this->artisan('geografia:vincular-municipios --aplicar')->assertSuccessful();
        $this->assertNotNull($plantel->fresh()->municipio_id);
    }
}
