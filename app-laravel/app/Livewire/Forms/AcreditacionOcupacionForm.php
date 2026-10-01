<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

class AcreditacionOcupacionForm extends Form
{
    public string $tipo = 'escritura_publica';

    public string $numeroEscritura = '';

    public string $notarioNombre = '';

    public string $notarioNumero = '';

    public string $notarioLocalidad = '';

    public string $folioRpp = '';

    public string $fechaInscripcionRpp = '';

    public string $arrendadorComodante = '';

    public string $arrendatarioComodatario = '';

    public string $fechaContrato = '';

    public string $vigenciaContrato = '';

    public string $usoAutorizado = '';

    public bool $ratificadoNotario = false;

    public string $otroEspecifique = '';

    #[Normalizar(Normalizacion::TextoLargo)]
    public string $observaciones = '';

    /**
     * Requeridos por variante de `tipo` (los 4 valores del CHECK de
     * acreditaciones_ocupacion_legal). El resto de campos por variante son
     * detalle notarial/contractual secundario, no obligatorio para
     * identificar la acreditación.
     */
    public function rules(): array
    {
        $escritura = $this->tipo === 'escritura_publica';
        $contrato = in_array($this->tipo, ['arrendamiento', 'comodato'], true);

        return [
            'tipo' => ['required', 'in:escritura_publica,arrendamiento,comodato,otro'],
            'numeroEscritura' => ReglasCaptura::texto(requerido: $escritura, max: 50),
            'notarioNombre' => ReglasCaptura::nombrePersona(requerido: $escritura, max: 150),
            'notarioNumero' => ReglasCaptura::texto(max: 20),
            'notarioLocalidad' => ReglasCaptura::texto(max: 100),
            'folioRpp' => ReglasCaptura::texto(max: 50),
            'fechaInscripcionRpp' => ReglasCaptura::fechaPasada(),
            'arrendadorComodante' => ReglasCaptura::texto(requerido: $contrato, max: 200),
            'arrendatarioComodatario' => ReglasCaptura::texto(requerido: $contrato, max: 200),
            'fechaContrato' => ReglasCaptura::fechaPasada(requerido: $contrato),
            'vigenciaContrato' => [...ReglasCaptura::fecha(requerido: $contrato), $this->noAnteriorAlContrato(...)],
            'usoAutorizado' => ReglasCaptura::texto(max: 200),
            'ratificadoNotario' => ['nullable', 'boolean'],
            'otroEspecifique' => ReglasCaptura::texto(requerido: $this->tipo === 'otro', max: 200),
            'observaciones' => ReglasCaptura::texto(max: 1000),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'tipo' => 'tipo de acreditación',
            'numeroEscritura' => 'número de escritura',
            'notarioNombre' => 'nombre del notario',
            'notarioNumero' => 'número del notario',
            'notarioLocalidad' => 'localidad del notario',
            'folioRpp' => 'folio del Registro Público de la Propiedad',
            'fechaInscripcionRpp' => 'fecha de inscripción RPP',
            'arrendadorComodante' => 'arrendador / comodante',
            'arrendatarioComodatario' => 'arrendatario / comodatario',
            'fechaContrato' => 'fecha del contrato',
            'vigenciaContrato' => 'vigencia del contrato',
            'usoAutorizado' => 'uso autorizado',
            'ratificadoNotario' => 'ratificado ante notario',
            'otroEspecifique' => 'especifique',
            'observaciones' => 'observaciones',
        ];
    }

    /** Solo compara cuando ambas fechas ya tienen formato válido; cada una tiene su propia regla de formato. */
    private function noAnteriorAlContrato(string $atributo, mixed $vigencia, \Closure $fallar): void
    {
        $esFecha = fn (mixed $valor): bool => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1;

        if ($esFecha($vigencia) && $esFecha($this->fechaContrato) && $vigencia < $this->fechaContrato) {
            $fallar('La vigencia del contrato no puede ser anterior a la fecha del contrato.');
        }
    }
}
