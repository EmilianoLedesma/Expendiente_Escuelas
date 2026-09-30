<?php

namespace Tests\Unit\Domain\Validaciones\Documental\Reglas;

use App\Domain\Validaciones\Documental\CatalogoReglasDocumentales;
use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\Hecho;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use App\Domain\Validaciones\Documental\Reglas\CantidadCoincide;
use App\Domain\Validaciones\Documental\Reglas\DocumentosRequeridosPresentes;
use App\Domain\Validaciones\Documental\Reglas\InventarioConLaboratorio;
use App\Domain\Validaciones\Documental\Reglas\ReciboNoReutilizado;
use App\Domain\Validaciones\Documental\TipoHecho;
use App\Domain\Validaciones\Resultado\EstadoResultado;
use PHPUnit\Framework\TestCase;

/** Per-level checks made possible by WS-5b (Paso 2.4 documentos por nivel). */
class ReglasDeNivelTest extends TestCase
{
    private const RECIBO = 'recibo_pago_derechos';

    private const ACERVO = 'acervo_bibliografico_primaria';

    /**
     * @param  list<Hecho>  $hechos
     * @param  list<string>  $foliosAjenos
     */
    private function contexto(array $hechos, array $presentes, array $foliosAjenos = [], ?array $requeridos = null): ContextoValidacion
    {
        return new ContextoValidacion('fisica', $hechos, $requeridos ?? $presentes, $presentes, $foliosAjenos);
    }

    // --- Recibo no reutilizado ---------------------------------------------

    public function test_recibo_con_folio_propio_cumple(): void
    {
        $contexto = $this->contexto([Hecho::deDocumento(TipoHecho::FolioRecibo, 'F-0001', self::RECIBO)], [self::RECIBO], ['F-0002']);

        $resultado = (new ReciboNoReutilizado)->evaluar($contexto);

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('recibo_no_reutilizado', $resultado->clave);
    }

    public function test_recibo_con_folio_registrado_en_otro_tramite_no_cumple_ignorando_mayusculas_y_espacios(): void
    {
        $contexto = $this->contexto([Hecho::deDocumento(TipoHecho::FolioRecibo, ' f-0001 ', self::RECIBO)], [self::RECIBO], ['F-0001']);

        $resultado = (new ReciboNoReutilizado)->evaluar($contexto);

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame([self::RECIBO], $resultado->documentos);
        $this->assertSame(' f-0001 ', $resultado->detalles['folio']);
    }

    public function test_recibo_subido_sin_folio_no_cumple(): void
    {
        $resultado = (new ReciboNoReutilizado)->evaluar($this->contexto([], [self::RECIBO]));

        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame([self::RECIBO], $resultado->documentos);
    }

    public function test_sin_recibo_subido_no_es_evaluable(): void
    {
        $this->assertSame(EstadoResultado::NoEvaluable, (new ReciboNoReutilizado)->evaluar($this->contexto([], []))->estado);
    }

    // --- Acervo: relación vs. biblioteca declarada ------------------------

    private function acervo(): CantidadCoincide
    {
        return new CantidadCoincide('acervo_coincide', TipoHecho::TitulosAcervo, ['acervo_bibliografico_primaria', 'acervo_bibliografico_secundaria']);
    }

    public function test_acervo_igual_al_declarado_en_infraestructura_cumple(): void
    {
        $contexto = $this->contexto([
            Hecho::declarado(TipoHecho::TitulosAcervo, '320'),
            Hecho::deDocumento(TipoHecho::TitulosAcervo, '320', self::ACERVO),
        ], [self::ACERVO]);

        $this->assertSame(EstadoResultado::Cumple, $this->acervo()->evaluar($contexto)->estado);
    }

    public function test_acervo_distinto_alerta_y_senala_la_relacion(): void
    {
        $contexto = $this->contexto([
            Hecho::declarado(TipoHecho::TitulosAcervo, '320'),
            Hecho::deDocumento(TipoHecho::TitulosAcervo, '300', self::ACERVO),
        ], [self::ACERVO]);

        $resultado = $this->acervo()->evaluar($contexto);

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
        $this->assertSame([self::ACERVO], $resultado->documentos);
    }

    public function test_relacion_subida_sin_numero_de_titulos_no_cumple(): void
    {
        $contexto = $this->contexto([Hecho::declarado(TipoHecho::TitulosAcervo, '320')], [self::ACERVO]);

        $this->assertSame(EstadoResultado::NoCumple, $this->acervo()->evaluar($contexto)->estado);
    }

    public function test_sin_biblioteca_declarada_no_es_evaluable(): void
    {
        $contexto = $this->contexto([Hecho::deDocumento(TipoHecho::TitulosAcervo, '300', self::ACERVO)], [self::ACERVO]);

        $this->assertSame(EstadoResultado::NoEvaluable, $this->acervo()->evaluar($contexto)->estado);
    }

    // --- Inventario de laboratorio vs. laboratorio declarado ---------------

    public function test_inventario_con_laboratorio_declarado_cumple(): void
    {
        $contexto = $this->contexto([Hecho::declarado(TipoHecho::LaboratoriosDeclarados, '1')], ['inventario_laboratorio']);

        $resultado = (new InventarioConLaboratorio)->evaluar($contexto);

        $this->assertSame(EstadoResultado::Cumple, $resultado->estado);
        $this->assertSame('inventario_con_laboratorio', $resultado->clave);
    }

    public function test_inventario_sin_laboratorio_declarado_alerta(): void
    {
        $contexto = $this->contexto([Hecho::declarado(TipoHecho::LaboratoriosDeclarados, '0')], ['inventario_laboratorio']);

        $resultado = (new InventarioConLaboratorio)->evaluar($contexto);

        $this->assertSame(EstadoResultado::Advertencia, $resultado->estado);
        $this->assertSame(['inventario_laboratorio'], $resultado->documentos);
    }

    public function test_sin_inventario_subido_no_es_evaluable(): void
    {
        $contexto = $this->contexto([Hecho::declarado(TipoHecho::LaboratoriosDeclarados, '0')], [], requeridos: ['inventario_laboratorio']);

        $this->assertSame(EstadoResultado::NoEvaluable, (new InventarioConLaboratorio)->evaluar($contexto)->estado);
    }

    // --- Documentos del nivel presentes -----------------------------------

    public function test_documentos_del_nivel_usan_su_propia_clave(): void
    {
        $regla = new DocumentosRequeridosPresentes(DocumentosRequeridosPresentes::CLAVE_NIVEL);

        $resultado = $regla->evaluar($this->contexto([], ['formato_solicitud'], requeridos: ['formato_solicitud', self::RECIBO]));

        $this->assertSame('documentos_nivel_presentes', $resultado->clave);
        $this->assertSame(EstadoResultado::NoCumple, $resultado->estado);
        $this->assertSame([self::RECIBO], $resultado->documentos);
    }

    // --- Catalog ---------------------------------------------------------

    /** @return list<string> */
    private function claves(array $requeridos): array
    {
        $contexto = new ContextoValidacion('fisica', [], [], []);

        return array_map(fn (ReglaDocumental $r) => $r->evaluar($contexto)->clave, CatalogoReglasDocumentales::reglasDeNivel($requeridos));
    }

    public function test_el_catalogo_de_nivel_solo_incluye_lo_que_aplica(): void
    {
        $this->assertSame(
            ['documentos_nivel_presentes', 'recibo_no_reutilizado'],
            $this->claves(['formato_solicitud', 'recibo_pago_derechos']),
        );
        $this->assertSame(
            ['documentos_nivel_presentes', 'recibo_no_reutilizado', 'acervo_coincide'],
            $this->claves(['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_primaria']),
        );
        $this->assertSame(
            ['documentos_nivel_presentes', 'recibo_no_reutilizado', 'acervo_coincide', 'inventario_con_laboratorio'],
            $this->claves(['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_secundaria', 'inventario_laboratorio']),
        );
    }
}
