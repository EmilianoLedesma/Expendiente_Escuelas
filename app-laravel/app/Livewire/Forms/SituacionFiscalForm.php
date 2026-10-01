<?php

namespace App\Livewire\Forms;

use App\Application\Validaciones\FormatosIdentificador;
use Livewire\Form;

class SituacionFiscalForm extends Form
{
    public string $nombre = '';

    public string $rfc = '';

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'rfc' => ['required', 'string', 'between:12,13', 'regex:'.FormatosIdentificador::RFC],
        ];
    }

    public function normalizar(): void
    {
        $this->nombre = trim($this->nombre);
        $this->rfc = mb_strtoupper(trim($this->rfc));
    }
}
