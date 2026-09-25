<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('tramite.index', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
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

    @if (session('status') == 'verification-link-sent')
        <x-ui.alert tipo="success" class="mb-lg">Enviamos un nuevo enlace de verificación al correo que registraste.</x-ui.alert>
    @endif

    <div class="flex flex-col-reverse gap-md border-t border-hairline pt-lg sm:flex-row sm:items-center sm:justify-between">
        <button wire:click="logout" type="button" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">Cerrar sesión</button>
        <x-ui.button-primary type="button" wire:click="sendVerification" wire:loading.attr="disabled" wire:target="sendVerification">Reenviar correo de verificación</x-ui.button-primary>
    </div>
</div>
