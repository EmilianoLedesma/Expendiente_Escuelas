<?php

namespace App\Http\Controllers\Tramite;

use App\Application\Tramite\ResumenTramite;
use App\Models\Escuela;
use Illuminate\Contracts\View\View;

class ResumenTramiteController
{
    public function __invoke(Escuela $escuela, ResumenTramite $resumenTramite): View
    {
        return view('tramite.resumen', ['resumen' => $resumenTramite->paraEscuela($escuela->id)]);
    }
}
