<?php

namespace App\Livewire\Forms;

use Livewire\Form;

class DictamenUsoSueloForm extends Form
{
    public string $fechaEmision = '';

    public function rules(): array
    {
        return [
            'fechaEmision' => ['required', 'date'],
        ];
    }
}
