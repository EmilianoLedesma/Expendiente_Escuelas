<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CuentaActiva
{
    public const MENSAJE = 'Cuenta archivada, contacta a un administrador.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->archivado_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('aviso_login', self::MENSAJE);
        }

        return $next($request);
    }
}
