<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\ListarExpedientes;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Expediente del supervisor: una lista de los niveles por estado (aprobados al abrir), con búsqueda; cada fila abre el
 * trámite en solo lectura. Presentación pura: la regla (rol, estados ofrecidos) vive en ListarExpedientes. Un rol distinto es un 403.
 */
#[Layout('layouts.tramite', ['title' => 'Expedientes'])]
class Expedientes extends Component
{
    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'estado', except: 'aprobado')]
    public string $estado = 'aprobado';

    public function render(ListarExpedientes $listar)
    {
        // Un estado que el caso de uso no ofrece (URL o cliente manipulados) vuelve al predeterminado, sin error.
        if (! array_key_exists($this->estado, ListarExpedientes::ESTADOS)) {
            $this->estado = 'aprobado';
        }

        try {
            $filas = $listar->ejecutar((int) auth()->id(), $this->estado, $this->buscar);
        } catch (PrecondicionIncumplida $e) {
            abort_if($e->etapaFaltante === 'rol', 403);
            throw $e;
        }

        return view('livewire.sedeq.expedientes', ['filas' => $filas, 'estados' => ListarExpedientes::ESTADOS]);
    }
}
