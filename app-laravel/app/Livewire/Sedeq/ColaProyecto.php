<?php

namespace App\Livewire\Sedeq;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\ListarColaProyecto;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cola del evaluador del proyecto educativo: los niveles de media superior en revisión, el que más espera primero.
 * Sin asignación; ListarColaProyecto filtra, aquí solo se presenta. El acceso lo impone `rol:evaluador_proyecto`.
 */
#[Layout('layouts.tramite', ['title' => 'Proyectos educativos'])]
class ColaProyecto extends Component
{
    public function render()
    {
        try {
            $filas = app(ListarColaProyecto::class)->ejecutar((int) auth()->id());
        } catch (PrecondicionIncumplida $e) {
            abort_if($e->etapaFaltante === 'rol', 403);
            throw $e;
        }

        return view('livewire.sedeq.cola-proyecto', ['filas' => $filas]);
    }
}
