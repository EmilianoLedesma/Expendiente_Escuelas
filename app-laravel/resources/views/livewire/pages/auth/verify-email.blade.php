<?php

use App\Application\Revision\RutaInicio;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): bool
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: RutaInicio::para(Auth::user()), navigate: true);

            return true;
        }

        Auth::user()->sendEmailVerificationNotification();

        $this->dispatch('toast', tipo: 'success', titulo: 'Enlace enviado', descripcion: 'Enviamos un nuevo enlace de verificación al correo que registraste.');

        // true = el envío quedó en cola (no confirma que el correo llegó).
        return true;
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-ui.page-header title="Verifica tu correo electrónico">
        <x-slot:intro>Gracias por registrarte. Antes de continuar, abre el enlace de verificación que enviamos a tu correo electrónico. Si no lo recibiste, podemos enviarte otro.</x-slot:intro>
    </x-ui.page-header>

    <div class="flex flex-col-reverse gap-md border-t border-hairline pt-lg sm:flex-row sm:items-center sm:justify-between">
        <button wire:click="logout" type="button" class="inline-flex min-h-11 items-center font-semibold text-primary">Cerrar sesión</button>
        <x-ui.stateful-button action="sendVerification" label="Reenviar correo de verificación" success-label="Solicitud de envío realizada" error-label="No se pudo enviar. Reintentar" :reset-after="4000" />
    </div>
</div>
