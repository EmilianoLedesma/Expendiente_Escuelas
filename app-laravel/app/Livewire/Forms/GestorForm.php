<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class GestorForm extends Form
{
    public string $nombre = '';

    public string $numeroPoder = '';

    public string $notarioNombre = '';

    public string $notarioNumero = '';

    public string $fechaPoder = '';

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'numeroPoder' => ['nullable', 'string', 'max:50'],
            'notarioNombre' => ['nullable', 'string', 'max:150'],
            'notarioNumero' => ['nullable', 'string', 'max:20'],
            'fechaPoder' => ['nullable', 'date'],
        ];
    }
}
