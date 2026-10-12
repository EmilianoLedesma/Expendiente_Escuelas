<?php

namespace App\Livewire\Forms;

use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

class DictamenUsoSueloForm extends Form
{
    public string $fechaEmision = '';

    public string $autoridadExpide = '';

    public string $folioDictamen = '';

    public string $giroAutorizado = '';

    public function rules(): array
    {
        return [
            'fechaEmision' => ReglasCaptura::fechaPasada(requerido: true),
            'autoridadExpide' => ReglasCaptura::texto(requerido: true, max: 200),
            'folioDictamen' => ReglasCaptura::texto(requerido: true, max: 50),
            'giroAutorizado' => ReglasCaptura::texto(requerido: true, max: 200),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'fechaEmision' => 'fecha de emisión',
            'autoridadExpide' => 'autoridad que expide',
            'folioDictamen' => 'folio del dictamen',
            'giroAutorizado' => 'giro que autoriza',
        ];
    }
}
