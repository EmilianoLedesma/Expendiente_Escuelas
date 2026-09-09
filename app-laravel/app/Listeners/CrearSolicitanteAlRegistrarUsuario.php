<?php

namespace App\Listeners;

use App\Models\Solicitante;
use Illuminate\Auth\Events\Registered;

class CrearSolicitanteAlRegistrarUsuario
{
    public function handle(Registered $event): void
    {
        Solicitante::firstOrCreate(['user_id' => $event->user->getAuthIdentifier()]);
    }
}
