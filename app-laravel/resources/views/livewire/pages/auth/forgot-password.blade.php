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
    public function sendPasswordResetLink(): void
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

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <x-ui.page-header title="Recuperar contraseña">
        <x-slot:intro>Indica tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</x-slot:intro>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tipo="success" class="mb-lg">{{ session('status') }}</x-ui.alert>
    @endif

    <form novalidate wire:submit="sendPasswordResetLink" class="space-y-md">
        <x-ui.error-summary />
        <x-ui.field id="email" label="Correo electrónico"><x-ui.input type="email" wire:model.blur="email" autofocus autocomplete="username" /></x-ui.field>
        <div class="flex justify-end border-t border-hairline pt-lg">
            <x-ui.button-primary type="submit" wire:loading.attr="disabled" wire:target="sendPasswordResetLink">Enviar enlace</x-ui.button-primary>
        </div>
    </form>
</div>
