<?php

namespace App\Http\Controllers\Auth;

use App\Application\Revision\RutaInicio;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $inicio = RutaInicio::para($request->user()).'?verified=1';

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($inicio);
        }

        $request->fulfill();

        return redirect()->intended($inicio)
            ->with('toast', ['tipo' => 'success', 'titulo' => 'Correo verificado', 'descripcion' => 'Ya puedes iniciar tu trámite.']);
    }
}
