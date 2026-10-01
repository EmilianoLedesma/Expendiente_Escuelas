<?php

namespace App\Livewire\Forms;

use App\Application\Validaciones\FormatosIdentificador;
use Livewire\Form;

/** INE and Constancia de CURP: name and CURP exactly as printed (ADR-007). */
class IdentidadDocumentoForm extends Form
{
    public string $nombre = '';

    public string $curp = '';

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'curp' => ['required', 'string', 'size:18', 'regex:'.FormatosIdentificador::CURP],
        ];
    }

    public function normalizar(): void
    {
        $this->nombre = trim($this->nombre);
        $this->curp = mb_strtoupper(trim($this->curp));
    }
}
