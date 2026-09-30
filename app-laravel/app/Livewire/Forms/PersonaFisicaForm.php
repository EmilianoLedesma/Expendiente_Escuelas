<?php

namespace App\Livewire\Forms;

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use App\Application\Captura\ReglasCaptura;
use Livewire\Form;

class PersonaFisicaForm extends Form
{
    public string $nombre = '';

    public string $fechaNacimiento = '';

    #[Normalizar(Normalizacion::Identificador)]
    public string $rfc = '';

    #[Normalizar(Normalizacion::Identificador)]
    public string $curp = '';

    public function rules(): array
    {
        return [
            'nombre' => ReglasCaptura::nombrePersona(requerido: true, max: 200),
            'fechaNacimiento' => ReglasCaptura::fechaPasada(),
            'rfc' => ReglasCaptura::rfcPersonaFisica(),
            'curp' => ReglasCaptura::curp(),
        ];
    }

    /** Los Form objects validan con la clave sin prefijo ("nombre"), así que su nombre visible vive aquí. */
    public function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre completo',
            'fechaNacimiento' => 'fecha de nacimiento',
            'rfc' => 'RFC',
            'curp' => 'CURP',
        ];
    }
}
