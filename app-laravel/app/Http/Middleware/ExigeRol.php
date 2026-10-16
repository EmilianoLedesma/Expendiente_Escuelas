<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `rol:supervisor` o `rol:supervisor|revisor`: 403 si el usuario no tiene ninguno de los roles. */
class ExigeRol
{
    public function handle(Request $request, Closure $next, string $rol): Response
    {
        abort_unless($request->user()?->hasAnyRole(explode('|', $rol)), 403);

        return $next($request);
    }
}
