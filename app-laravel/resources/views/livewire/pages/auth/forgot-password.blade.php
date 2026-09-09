<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
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
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Recuperar contraseña</h1>
    <p class="font-sans text-body-sm text-body mb-lg">
        Indica tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
    </p>

    @if (session('status'))
        <div class="mb-lg">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <form wire:submit="sendPasswordResetLink" class="space-y-md">
        <x-ui.text-input name="email" label="Correo electrónico" type="email" wire:model="email" required autofocus />

        <div class="flex justify-end pt-md border-t-[0.5px] border-hairline">
            <x-ui.button-primary type="submit">Enviar enlace</x-ui.button-primary>
        </div>
    </form>
</div>
