<?php

namespace App\Livewire\Forms;

use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

class ConstanciaSeguridadForm extends Form
{
    public string $fechaEmision = '';

    public string $peritoNombre = '';

    public string $peritoCedulaProfesional = '';

    public string $peritoRegistroDro = '';

    public string $peritoRegistroAutoridad = '';

    public string $peritoRegistroVigencia = '';

    public function rules(): array
    {
        return [
            'fechaEmision' => ReglasCaptura::fechaPasada(requerido: true),
            'peritoNombre' => ReglasCaptura::nombrePersona(requerido: true, max: 200),
            'peritoCedulaProfesional' => ReglasCaptura::texto(max: 50),
            'peritoRegistroDro' => ReglasCaptura::texto(requerido: true, max: 50),
            'peritoRegistroAutoridad' => ReglasCaptura::texto(max: 150),
            'peritoRegistroVigencia' => ReglasCaptura::fecha(),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'fechaEmision' => 'fecha de emisión',
            'peritoNombre' => 'nombre del perito',
            'peritoCedulaProfesional' => 'cédula profesional del perito',
            'peritoRegistroDro' => 'número de registro DRO',
            'peritoRegistroAutoridad' => 'autoridad del registro',
            'peritoRegistroVigencia' => 'vigencia del registro',
        ];
    }
}
