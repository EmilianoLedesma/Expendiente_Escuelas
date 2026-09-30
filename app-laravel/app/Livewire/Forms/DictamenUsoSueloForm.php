<?php

namespace App\Livewire\Forms;

use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

class DictamenUsoSueloForm extends Form
{
    public string $fechaEmision = '';

    public function rules(): array
    {
        return [
            'fechaEmision' => ReglasCaptura::fechaPasada(requerido: true),
        ];
    }

    public function validationAttributes(): array
    {
        return ['fechaEmision' => 'fecha de emisión'];
    }
}
