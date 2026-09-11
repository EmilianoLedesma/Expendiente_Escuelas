<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class ConstanciaSeguridadForm extends Form
{
    public string $fechaEmision = '';

    public string $peritoNombre = '';

    public string $peritoCedulaProfesional = '';

    public string $peritoRegistroDro = '';

    public string $peritoRegistroAutoridad = '';

    public string $peritoRegistroVigencia = '';

    public function rules(): array
    {
        return [
            'fechaEmision' => ['required', 'date'],
            'peritoNombre' => ['required', 'string', 'max:200'],
            'peritoCedulaProfesional' => ['nullable', 'string', 'max:50'],
            'peritoRegistroDro' => ['required', 'string', 'max:50'],
            'peritoRegistroAutoridad' => ['nullable', 'string', 'max:150'],
            'peritoRegistroVigencia' => ['nullable', 'date'],
        ];
    }
}
