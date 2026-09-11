<?php

namespace App\Livewire\Forms;

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

    public string $observaciones = '';

    /**
     * Requeridos por variante de `tipo` (los 4 valores del CHECK de
     * acreditaciones_ocupacion_legal). El resto de campos por variante son
     * detalle notarial/contractual secundario, no obligatorio para
     * identificar la acreditación.
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:escritura_publica,arrendamiento,comodato,otro'],
            'numeroEscritura' => [$this->tipo === 'escritura_publica' ? 'required' : 'nullable', 'string', 'max:50'],
            'notarioNombre' => [$this->tipo === 'escritura_publica' ? 'required' : 'nullable', 'string', 'max:150'],
            'notarioNumero' => ['nullable', 'string', 'max:20'],
            'notarioLocalidad' => ['nullable', 'string', 'max:100'],
            'folioRpp' => ['nullable', 'string', 'max:50'],
            'fechaInscripcionRpp' => ['nullable', 'date'],
            'arrendadorComodante' => [in_array($this->tipo, ['arrendamiento', 'comodato'], true) ? 'required' : 'nullable', 'string', 'max:200'],
            'arrendatarioComodatario' => [in_array($this->tipo, ['arrendamiento', 'comodato'], true) ? 'required' : 'nullable', 'string', 'max:200'],
            'fechaContrato' => [in_array($this->tipo, ['arrendamiento', 'comodato'], true) ? 'required' : 'nullable', 'date'],
            'vigenciaContrato' => [in_array($this->tipo, ['arrendamiento', 'comodato'], true) ? 'required' : 'nullable', 'date'],
            'usoAutorizado' => ['nullable', 'string', 'max:200'],
            'ratificadoNotario' => ['nullable', 'boolean'],
            'otroEspecifique' => [$this->tipo === 'otro' ? 'required' : 'nullable', 'string', 'max:200'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
