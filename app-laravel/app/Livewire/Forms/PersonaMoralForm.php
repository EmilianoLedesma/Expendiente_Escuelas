<?php

namespace App\Livewire\Forms;

use App\Application\Captura\ReglasCaptura;
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
            'razonSocial' => ReglasCaptura::texto(requerido: true, max: 200),
            'numeroEscrituraConstitutiva' => ReglasCaptura::texto(max: 50),
            'fechaEscrituraConstitutiva' => ReglasCaptura::fechaPasada(),
            'notarioNombre' => ReglasCaptura::nombrePersona(max: 150),
            'notarioNumero' => ReglasCaptura::texto(max: 20),
            'notarioCiudad' => ReglasCaptura::texto(max: 100),
            'folioRegistroPublico' => ReglasCaptura::texto(max: 50),
            'fechaInscripcionRpp' => ReglasCaptura::fechaPasada(),
            'nombreRepresentanteLegal' => ReglasCaptura::nombrePersona(requerido: true, max: 200),
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'razonSocial' => 'razón social',
            'numeroEscrituraConstitutiva' => 'número de escritura constitutiva',
            'fechaEscrituraConstitutiva' => 'fecha de escritura constitutiva',
            'notarioNombre' => 'nombre del notario',
            'notarioNumero' => 'número de notaría',
            'notarioCiudad' => 'ciudad de la notaría',
            'folioRegistroPublico' => 'folio del Registro Público',
            'fechaInscripcionRpp' => 'fecha de inscripción en el Registro Público',
            'nombreRepresentanteLegal' => 'representante legal',
        ];
    }
}
