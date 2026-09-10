<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PersonaFisicaForm extends Form
{
    public string $nombre = '';

    public string $fechaNacimiento = '';

    public string $rfc = '';

    public string $curp = '';

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'fechaNacimiento' => ['nullable', 'date'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'curp' => ['nullable', 'string', 'max:18'],
        ];
    }
}
