<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class PersonaMoralForm extends Form
{
    public string $razonSocial = '';

    public string $numeroEscrituraConstitutiva = '';

    public string $fechaEscrituraConstitutiva = '';

    public string $notarioNombre = '';

    public string $notarioNumero = '';

    public string $notarioCiudad = '';

    public string $folioRegistroPublico = '';

    public string $fechaInscripcionRpp = '';

    public string $nombreRepresentanteLegal = '';

    public function rules(): array
    {
        return [
            'razonSocial' => ['required', 'string', 'max:200'],
            'numeroEscrituraConstitutiva' => ['nullable', 'string', 'max:50'],
            'fechaEscrituraConstitutiva' => ['nullable', 'date'],
            'notarioNombre' => ['nullable', 'string', 'max:150'],
            'notarioNumero' => ['nullable', 'string', 'max:20'],
            'notarioCiudad' => ['nullable', 'string', 'max:100'],
            'folioRegistroPublico' => ['nullable', 'string', 'max:50'],
            'fechaInscripcionRpp' => ['nullable', 'date'],
            'nombreRepresentanteLegal' => ['required', 'string', 'max:200'],
        ];
    }
}
