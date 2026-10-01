<?php

namespace Tests\Feature\Application\Validaciones;

use App\Application\Validaciones\ConstruirContextoValidacion;
use App\Application\Validaciones\DTO\FilaValidacion;
use App\Application\Validaciones\DTO\SeccionNivel;
use App\Application\Validaciones\EjecutarValidacionFinal;
use App\Application\Validaciones\UltimaValidacionFinal;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Models\EscuelaNivel;
use App\Models\EvaluacionValidacion;
use App\Models\InstalacionEspacio;
use App\Models\ReciboPagoDerechos;
use App\Models\TipoEspacio;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CapturaExpedienteConsistente;
use Tests\TestCase;

/** WS-5b checks per escuela_nivel: recibo, acervo, inventario, level checklist (ADR-007 addendum). */
class ValidacionPorNivelTest extends TestCase
{
    use CapturaExpedienteConsistente;
    use RefreshDatabase;

    private function tramiteCompleto(): EscuelaNivel
    {
        $escuela = $this->crearEscuelaConPlantel();
        $this->completarTramite($escuela);

        return EscuelaNivel::where('escuela_id', $escuela->id)->sole();
    }

    private function folio(EscuelaNivel $escuelaNivel): string
    {
        return ReciboPagoDerechos::query()
            ->join('documentos_escuela_nivel', 'documentos_escuela_nivel.id', '=', 'recibos_pago_derechos.documento_escuela_nivel_id')
            ->where('documentos_escuela_nivel.escuela_nivel_id', $escuelaNivel->id)
            ->value('folio');
    }

    private function declararEspacio(EscuelaNivel $escuelaNivel, string $clave, ?int $cantidad, array $titulosPorMaterial = []): void
    {
        (new TiposEspaciosSeeder)->run();
        $espacio = InstalacionEspacio::create([
            'plantel_id' => $escuelaNivel->escuela->plantel_id,
            'tipo_espacio_id' => TipoEspacio::where('clave', $clave)->value('id'),
            'cantidad' => $cantidad,
        ]);

        foreach ($titulosPorMaterial as $material => $titulos) {
            DB::table('biblioteca_materiales')->insert([
                'instalacion_espacio_id' => $espacio->id,
                'tipo_material_id' => DB::table('tipos_material_biblioteca')->where('clave', $material)->value('id'),
                'numero_titulos' => $titulos,
            ]);
        }
    }

    private function valor(array $hechos, TipoHecho $tipo, ?string $documento): ?string
    {
        foreach ($hechos as $hecho) {
            if ($hecho->tipo === $tipo && $hecho->documentoClave === $documento) {
                return $hecho->valor;
            }
        }

        return null;
    }

    public function test_el_contexto_del_nivel_lee_el_recibo_el_acervo_y_los_folios_de_otros_niveles(): void
    {
        $propio = $this->tramiteCompleto();
        $ajeno = $this->tramiteCompleto();
        $this->declararEspacio($propio, 'biblioteca', 1, ['libros' => 250, 'revistas_especializadas' => 40]);

        $contexto = app(ConstruirContextoValidacion::class)->paraNivel($propio->id);

        $this->assertSame($this->folio($propio), $this->valor($contexto->hechos, TipoHecho::FolioRecibo, 'recibo_pago_derechos'));
        $this->assertSame([], $contexto->foliosAjenos, 'Solo se traen los folios ajenos que coinciden con el propio.');
        $this->assertSame('300', $this->valor($contexto->hechos, TipoHecho::TitulosAcervo, 'acervo_bibliografico_primaria'));
        $this->assertSame('250', $this->valor($contexto->hechos, TipoHecho::TitulosAcervo, null), 'Solo cuentan los libros de la biblioteca.');
        $this->assertSame('0', $this->valor($contexto->hechos, TipoHecho::LaboratoriosDeclarados, null));
        $this->assertSame(['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_primaria'], $contexto->clavesRequeridas);
        $this->assertSame($contexto->clavesRequeridas, $contexto->clavesPresentes);
    }

    public function test_el_contexto_trae_solo_los_folios_ajenos_que_coinciden_ignorando_mayusculas_y_espacios(): void
    {
        $propio = $this->tramiteCompleto();
        $ajeno = $this->tramiteCompleto();
        $this->tramiteCompleto();
        $copiado = ' '.strtolower($this->folio($propio)).' ';
        ReciboPagoDerechos::where('folio', $this->folio($ajeno))->update(['folio' => $copiado]);

        $contexto = app(ConstruirContextoValidacion::class)->paraNivel($propio->id);

        $this->assertSame([$copiado], $contexto->foliosAjenos);
    }

    public function test_sin_biblioteca_declarada_no_hay_titulos_declarados_y_los_laboratorios_se_suman(): void
    {
        $escuelaNivel = $this->tramiteCompleto();
        $this->declararEspacio($escuelaNivel, 'laboratorio_polifuncional', 2);

        $contexto = app(ConstruirContextoValidacion::class)->paraNivel($escuelaNivel->id);

        $this->assertNull($this->valor($contexto->hechos, TipoHecho::TitulosAcervo, null));
        $this->assertSame('2', $this->valor($contexto->hechos, TipoHecho::LaboratoriosDeclarados, null));
    }

    public function test_cada_nivel_aparece_como_seccion_y_se_relee_igual(): void
    {
        $escuelaNivel = $this->tramiteCompleto();

        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuelaNivel->escuela_id);

        $this->assertTrue($validacion->listaParaEnvio);
        $this->assertCount(1, $validacion->niveles);
        $seccion = $validacion->niveles[0];
        $this->assertInstanceOf(SeccionNivel::class, $seccion);
        $this->assertSame($escuelaNivel->id, $seccion->escuelaNivelId);
        $this->assertSame('Primaria', $seccion->nivel);
        $this->assertSame(
            ['documentos_nivel_presentes' => 'cumple', 'recibo_no_reutilizado' => 'cumple', 'acervo_coincide' => 'no_evaluable'],
            array_column(array_map(fn (FilaValidacion $f) => $f->aArreglo(), $seccion->filas), 'estado', 'clave'),
        );
        $this->assertSame('Recibo de pago no usado en otro nivel', $seccion->filas[1]->titulo);

        $releida = app(UltimaValidacionFinal::class)->paraEscuela($escuelaNivel->escuela_id);
        $this->assertEquals($validacion->niveles, $releida?->niveles);
        $this->assertEquals($validacion->filas, $releida?->filas);
    }

    public function test_un_folio_reutilizado_bloquea_el_envio(): void
    {
        $propio = $this->tramiteCompleto();
        $ajeno = $this->tramiteCompleto();
        ReciboPagoDerechos::query()->where('folio', $this->folio($propio))->update(['folio' => strtolower($this->folio($ajeno))]);

        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($propio->escuela_id);

        $this->assertFalse($validacion->listaParaEnvio);
        $this->assertFalse(EvaluacionValidacion::where('escuela_id', $propio->escuela_id)->sole()->lista_para_envio);
        $fila = $validacion->niveles[0]->conEstado('no_cumple')[0];
        $this->assertSame('recibo_no_reutilizado', $fila->clave);
        $this->assertSame(['recibo_pago_derechos' => 'Recibo de pago de derechos'], $fila->documentos);
        $this->assertCount(1, $validacion->totalConEstado('no_cumple'));
    }

    public function test_un_acervo_distinto_al_declarado_alerta_sin_bloquear(): void
    {
        $escuelaNivel = $this->tramiteCompleto();
        $this->declararEspacio($escuelaNivel, 'biblioteca', 1, ['libros' => 320]);

        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuelaNivel->escuela_id);

        $this->assertTrue($validacion->listaParaEnvio);
        $fila = $validacion->niveles[0]->conEstado('advertencia')[0];
        $this->assertSame('acervo_coincide', $fila->clave);
        $this->assertContains('Declarado: 320', $fila->lineas);
    }

    public function test_una_evaluacion_guardada_antes_de_las_secciones_por_nivel_se_sigue_leyendo(): void
    {
        $escuelaNivel = $this->tramiteCompleto();
        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuelaNivel->escuela_id);
        EvaluacionValidacion::query()->update(['resultados' => json_encode(array_map(fn (FilaValidacion $f) => $f->aArreglo(), $validacion->filas))]);

        $releida = app(UltimaValidacionFinal::class)->paraEscuela($escuelaNivel->escuela_id);

        $this->assertEquals($validacion->filas, $releida?->filas);
        $this->assertSame([], $releida?->niveles);
    }

    public function test_el_pdf_incluye_una_seccion_por_nivel(): void
    {
        $escuelaNivel = $this->tramiteCompleto();
        $validacion = app(EjecutarValidacionFinal::class)->ejecutar($escuelaNivel->escuela_id);

        $html = view('pdf.reporte-validacion', [
            'escuela' => ['numero' => '0001', 'nombre' => null, 'domicilio' => 'Centro'],
            'listaParaEnvio' => $validacion->listaParaEnvio,
            'filas' => $validacion->filas,
            'capacidad' => $validacion->capacidad,
            'niveles' => $validacion->niveles,
            'generadaEn' => $validacion->generadaEn,
        ])->render();

        $this->assertStringContainsString('Documentos del nivel: Primaria', $html);
        $this->assertStringContainsString('Recibo de pago no usado en otro nivel', $html);
        $this->assertStringContainsString('Folio del recibo: F-'.$escuelaNivel->id, $html);
    }
}
