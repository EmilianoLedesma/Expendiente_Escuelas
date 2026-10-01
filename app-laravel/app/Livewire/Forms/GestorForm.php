<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

class GestorForm extends Form
{
    public string $nombre = '';

    public string $numeroPoder = '';

    public string $notarioNombre = '';

    public string $notarioNumero = '';

    public string $fechaPoder = '';

    /** ADR-007: the uploaded INE and Constancia de CURP are the gestor's. */
    #[Normalizar(Normalizacion::Identificador)]
    public string $curp = '';

    public function rules(): array
    {
        return [
            'nombre' => ReglasCaptura::nombrePersona(requerido: true, max: 200),
            'numeroPoder' => ReglasCaptura::texto(max: 50),
            'notarioNombre' => ReglasCaptura::nombrePersona(max: 150),
            'notarioNumero' => ReglasCaptura::texto(max: 20),
            'fechaPoder' => ReglasCaptura::fechaPasada(),
            'curp' => ReglasCaptura::curp(requerido: true),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre del gestor',
            'numeroPoder' => 'número de poder',
            'notarioNombre' => 'nombre del notario',
            'notarioNumero' => 'número de notaría',
            'fechaPoder' => 'fecha del poder',
            'curp' => 'CURP del gestor',
        ];
    }
}
