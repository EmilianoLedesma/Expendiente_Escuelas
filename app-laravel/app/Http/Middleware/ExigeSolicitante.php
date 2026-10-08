<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Páginas solo del solicitante (crear trámites, gestionar responsables): quien no tiene fila en solicitantes vuelve a /tramite. */
class ExigeSolicitante
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->solicitante === null) {
            return redirect()->route('tramite.index');
        }

        return $next($request);
    }
}
