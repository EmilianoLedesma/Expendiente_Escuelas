<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Tramite\ReenviarCorrecciones;
use App\Models\Escuela;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReenviarCorreccionesController
{
    public function __invoke(Request $request, Escuela $escuela, ReenviarCorrecciones $reenviar): RedirectResponse
    {
        $hub = redirect()->route('tramite.resumen', ['escuela' => $escuela->id]);

        try {
            $reenviar->ejecutar($escuela->id, $request->user()->id);
        } catch (PrecondicionIncumplida $e) {
            return $hub->with('toast', ['tipo' => 'error', 'titulo' => 'No se pudieron reenviar las correcciones', 'descripcion' => $e->getMessage()]);
        }

        return $hub->with('toast', ['tipo' => 'success', 'titulo' => 'Correcciones reenviadas', 'descripcion' => 'SEDEQ revisará de nuevo los pasos que corregiste.']);
    }
}
