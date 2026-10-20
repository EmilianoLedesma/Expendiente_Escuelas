<?php

namespace Tests\Unit\Domain\Documentos;

use App\Domain\Documentos\ComparadorDomicilios;
use App\Domain\Documentos\Domicilio;
use App\Domain\Documentos\ResultadoComparacion;
use App\Domain\Validaciones\Documental\NormalizadorDomicilio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * El comparador solo asiste: una diferencia de texto NO es una discrepancia jurídica. Devuelve coincidencia
 * acreditada o evidencia insuficiente; «discrepancia confirmada» solo puede venir de una decisión humana.
 */
class ComparadorDomiciliosTest extends TestCase
{
    private function comparar(Domicilio $a, Domicilio $b): ResultadoComparacion
    {
        return (new ComparadorDomicilios(new NormalizadorDomicilio))->comparar($a, $b)->resultado;
    }

    /** @param  array<string, string|int|null>  $campos */
    private function base(array $campos = []): Domicilio
    {
        return new Domicilio(...[
            'calle' => 'Avenida Hidalgo', 'numeroExt' => '12', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigoPostal' => '76000',
            ...$campos,
        ]);
    }

    /** @return array<string, array{0: array<string, ?string>}> */
    public static function diferenciasSoloDeFormato(): array
    {
        return [
            'mayúsculas y acentos' => [['calle' => 'AVENIDA HIDALGO', 'municipio' => 'QUERETARO']],
            'espacios repetidos' => [['calle' => '  Avenida    Hidalgo ', 'colonia' => 'Centro  ']],
            'Av. y Avenida' => [['calle' => 'Av. Hidalgo']],
            'prefijo Calle y Col.' => [['calle' => 'Calle Avenida Hidalgo', 'colonia' => 'Col. Centro']],
            'No. y Número' => [['numeroExt' => 'No. 12']],
            'código postal con espacios' => [['codigoPostal' => '76 000']],
        ];
    }

    /** @param  array<string, ?string>  $cambios */
    #[DataProvider('diferenciasSoloDeFormato')]
    public function test_las_diferencias_de_formato_no_rompen_la_coincidencia(array $cambios): void
    {
        $this->assertSame(ResultadoComparacion::CoincidenciaAcreditada, $this->comparar($this->base(), $this->base($cambios)));
    }

    public function test_una_calle_distinta_es_evidencia_insuficiente_y_no_una_discrepancia_confirmada(): void
    {
        $comparacion = (new ComparadorDomicilios(new NormalizadorDomicilio))->comparar($this->base(), $this->base(['calle' => 'Avenida Zaragoza']));

        $this->assertSame(ResultadoComparacion::EvidenciaInsuficiente, $comparacion->resultado);
        $this->assertSame(['calle'], $comparacion->partesQueDifieren);
    }

    public function test_un_codigo_postal_distinto_tampoco_se_declara_discrepancia(): void
    {
        $this->assertSame(ResultadoComparacion::EvidenciaInsuficiente, $this->comparar($this->base(), $this->base(['codigoPostal' => '76010'])));
    }

    public function test_una_parte_obligatoria_ausente_no_acredita_coincidencia(): void
    {
        $comparacion = (new ComparadorDomicilios(new NormalizadorDomicilio))->comparar($this->base(), $this->base(['colonia' => null]));

        $this->assertSame(ResultadoComparacion::EvidenciaInsuficiente, $comparacion->resultado);
        $this->assertContains('colonia', $comparacion->partesNoComparables);
    }

    public function test_sin_numero_exterior_declarado_en_un_lado_no_se_acredita(): void
    {
        $this->assertSame(ResultadoComparacion::EvidenciaInsuficiente, $this->comparar($this->base(['numeroExt' => null]), $this->base()));
    }

    public function test_s_n_en_ambos_lados_coincide_y_s_n_contra_un_numero_difiere(): void
    {
        $this->assertSame(ResultadoComparacion::CoincidenciaAcreditada, $this->comparar($this->base(['numeroExt' => 'S/N']), $this->base(['numeroExt' => 's / n'])));
        $this->assertSame(ResultadoComparacion::EvidenciaInsuficiente, $this->comparar($this->base(['numeroExt' => 'S/N']), $this->base(['numeroExt' => '12'])));
    }

    public function test_el_numero_interior_solo_se_compara_si_ambos_lo_traen(): void
    {
        $this->assertSame(ResultadoComparacion::CoincidenciaAcreditada, $this->comparar($this->base(['numeroInt' => 'A-3']), $this->base()));
        $this->assertSame(ResultadoComparacion::CoincidenciaAcreditada, $this->comparar($this->base(['numeroInt' => 'A-3']), $this->base(['numeroInt' => 'a 3'])));
        $this->assertSame(ResultadoComparacion::EvidenciaInsuficiente, $this->comparar($this->base(['numeroInt' => 'A-3']), $this->base(['numeroInt' => 'B-4'])));
    }

    public function test_nunca_devuelve_discrepancia_confirmada(): void
    {
        $comparador = new ComparadorDomicilios(new NormalizadorDomicilio);
        $totalmenteDistintos = new Domicilio('Otra calle', '99', null, 'Otra colonia', 'Corregidora', '76900');

        $this->assertNotSame(ResultadoComparacion::DiscrepanciaConfirmada, $comparador->comparar($this->base(), $totalmenteDistintos)->resultado);
    }

    public function test_no_modifica_los_valores_originales(): void
    {
        $a = $this->base(['calle' => 'Av. Hidalgo']);

        $this->comparar($a, $this->base());

        $this->assertSame('Av. Hidalgo', $a->calle);
    }

    /** Con clave del catálogo en ambas fuentes se compara la clave: «Qro.» y «Querétaro» con el mismo id coinciden. */
    public function test_con_id_de_municipio_en_ambas_fuentes_se_compara_el_id_y_no_el_texto(): void
    {
        $comparacion = (new ComparadorDomicilios(new NormalizadorDomicilio))->comparar(
            $this->base(['municipio' => 'Qro.', 'municipioId' => 14]),
            $this->base(['municipio' => 'QUERÉTARO', 'municipioId' => 14]),
        );

        $this->assertSame(ResultadoComparacion::CoincidenciaAcreditada, $comparacion->resultado);
        $this->assertContains('municipio', $comparacion->partesQueCoinciden);
    }

    public function test_ids_de_municipio_distintos_son_evidencia_insuficiente_aunque_el_texto_coincida(): void
    {
        $comparacion = (new ComparadorDomicilios(new NormalizadorDomicilio))->comparar(
            $this->base(['municipioId' => 14]),
            $this->base(['municipioId' => 6]),
        );

        $this->assertSame(ResultadoComparacion::EvidenciaInsuficiente, $comparacion->resultado);
        $this->assertContains('municipio', $comparacion->partesQueDifieren);
    }

    public function test_con_id_en_una_sola_fuente_se_compara_el_texto(): void
    {
        $this->assertSame(ResultadoComparacion::CoincidenciaAcreditada, $this->comparar($this->base(['municipioId' => 14]), $this->base()));
    }

    /** Una coincidencia de municipio no es coincidencia de domicilio. */
    public function test_el_mismo_municipio_no_basta_si_otra_parte_difiere(): void
    {
        $this->assertSame(ResultadoComparacion::EvidenciaInsuficiente, $this->comparar(
            $this->base(['municipioId' => 14, 'calle' => 'Zaragoza']),
            $this->base(['municipioId' => 14]),
        ));
    }
}
