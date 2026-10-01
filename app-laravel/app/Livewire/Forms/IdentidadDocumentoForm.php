<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

/** INE and Constancia de CURP: name and CURP exactly as printed (ADR-007). */
class IdentidadDocumentoForm extends Form
{
    public string $nombre = '';

    #[Normalizar(Normalizacion::Identificador)]
    public string $curp = '';

    public function rules(): array
    {
        return [
            'nombre' => ReglasCaptura::nombrePersona(requerido: true, max: 200),
            'curp' => ReglasCaptura::curp(requerido: true),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre como aparece en el documento',
            'curp' => 'CURP',
        ];
    }
}
