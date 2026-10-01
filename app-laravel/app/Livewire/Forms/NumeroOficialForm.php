<?php

namespace App\Livewire\Forms;

use App\Application\Validaciones\FormatosIdentificador;
use Livewire\Form;

/** Address as printed on the certificado de número oficial; sizes mirror planteles. */
class NumeroOficialForm extends Form
{
    public string $calle = '';

    public string $numeroExt = '';

    public string $colonia = '';

    public string $municipio = '';

    public string $codigoPostal = '';

    public function rules(): array
    {
        return [
            'calle' => ['required', 'string', 'max:150'],
            'numeroExt' => ['nullable', 'string', 'max:20'],
            'colonia' => ['required', 'string', 'max:150'],
            'municipio' => ['required', 'string', 'max:150'],
            'codigoPostal' => ['required', 'regex:'.FormatosIdentificador::CODIGO_POSTAL],
        ];
    }
}
