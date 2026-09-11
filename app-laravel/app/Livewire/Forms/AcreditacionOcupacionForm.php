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

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:escritura_publica,arrendamiento,comodato,otro'],
            'numeroEscritura' => ['nullable', 'string', 'max:50'],
            'notarioNombre' => ['nullable', 'string', 'max:150'],
            'notarioNumero' => ['nullable', 'string', 'max:20'],
            'notarioLocalidad' => ['nullable', 'string', 'max:100'],
            'folioRpp' => ['nullable', 'string', 'max:50'],
            'fechaInscripcionRpp' => ['nullable', 'date'],
            'arrendadorComodante' => ['nullable', 'string', 'max:200'],
            'arrendatarioComodatario' => ['nullable', 'string', 'max:200'],
            'fechaContrato' => ['nullable', 'date'],
            'vigenciaContrato' => ['nullable', 'date'],
            'usoAutorizado' => ['nullable', 'string', 'max:200'],
            'ratificadoNotario' => ['nullable', 'boolean'],
            'otroEspecifique' => ['nullable', 'string', 'max:200'],
        ];
    }
}
