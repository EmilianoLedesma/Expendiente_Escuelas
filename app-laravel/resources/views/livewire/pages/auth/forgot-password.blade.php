<?php

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Normalizar(Normalizacion::Correo)]
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): bool
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return false;
        }

        $this->reset('email');

        $this->dispatch('toast', tipo: 'success', titulo: 'Enlace enviado', descripcion: __($status));

        // true = el enlace quedó en cola (no confirma que el correo llegó).
        return true;
    }
}; ?>

<div>
    <x-ui.page-header title="Recuperar contraseña">
        <x-slot:intro>Indica tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</x-slot:intro>
    </x-ui.page-header>

    <form novalidate class="space-y-md">
        <x-ui.error-summary />
        <x-ui.field id="email" label="Correo electrónico"><x-ui.input type="email" wire:model.blur="email" autofocus autocomplete="username" /></x-ui.field>
        <div class="flex justify-end border-t border-hairline pt-lg">
            <x-ui.stateful-button type="submit" action="sendPasswordResetLink" label="Enviar enlace" success-label="Solicitud de envío realizada" error-label="No se pudo enviar. Reintentar" :reset-after="4000" />
        </div>
    </form>
</div>
