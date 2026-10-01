<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

/** Address as printed on the certificado de número oficial; sizes mirror planteles. */
class NumeroOficialForm extends Form
{
    public string $calle = '';

    public string $numeroExt = '';

    public string $colonia = '';

    public string $municipio = '';

    #[Normalizar(Normalizacion::Digitos)]
    public string $codigoPostal = '';

    public function rules(): array
    {
        return [
            'calle' => ReglasCaptura::texto(requerido: true, max: 150),
            'numeroExt' => ReglasCaptura::texto(max: 20),
            'colonia' => ReglasCaptura::texto(requerido: true, max: 150),
            'municipio' => ReglasCaptura::texto(requerido: true, max: 150),
            'codigoPostal' => ReglasCaptura::codigoPostal(requerido: true),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'calle' => 'calle',
            'numeroExt' => 'número exterior',
            'colonia' => 'colonia',
            'municipio' => 'municipio',
            'codigoPostal' => 'código postal',
        ];
    }
}
