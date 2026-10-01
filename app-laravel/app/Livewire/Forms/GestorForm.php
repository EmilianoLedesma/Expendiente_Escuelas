<?php

namespace App\Livewire\Forms;

use App\Application\Validaciones\FormatosIdentificador;
use Livewire\Form;

class GestorForm extends Form
{
    public string $nombre = '';

    public string $numeroPoder = '';

    public string $notarioNombre = '';

    public string $notarioNumero = '';

    public string $fechaPoder = '';

    /** ADR-007: the uploaded INE and Constancia de CURP are the gestor's. */
    public string $curp = '';

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'numeroPoder' => ['nullable', 'string', 'max:50'],
            'notarioNombre' => ['nullable', 'string', 'max:150'],
            'notarioNumero' => ['nullable', 'string', 'max:20'],
            'fechaPoder' => ['nullable', 'date'],
            'curp' => ['required', 'string', 'size:18', 'regex:'.FormatosIdentificador::CURP],
        ];
    }
}
