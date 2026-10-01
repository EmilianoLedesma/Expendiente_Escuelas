<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

class SituacionFiscalForm extends Form
{
    public string $nombre = '';

    #[Normalizar(Normalizacion::Identificador)]
    public string $rfc = '';

    public function rules(): array
    {
        return [
            'nombre' => ReglasCaptura::texto(requerido: true, max: 200),
            'rfc' => ReglasCaptura::rfc(requerido: true),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre o razón social',
            'rfc' => 'RFC',
        ];
    }
}
