<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $default = Auth::user()->hasRole('sedeq')
            ? '/admin'
            : route('tramite.preregistro', absolute: false);

        $this->redirectIntended(default: $default, navigate: true);
    }
}; ?>

<div>
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Iniciar sesión</h1>
    <p class="font-sans text-body-sm text-body mb-lg">
        Accede a tu cuenta para continuar con tus trámites.
    </p>

    @if (session('status'))
        <div class="mb-lg">
            <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
        </div>
    @endif

    <form wire:submit="login" class="space-y-md">
        <x-ui.text-input name="form.email" label="Correo electrónico" type="email" wire:model="form.email" required autofocus autocomplete="username" />

        <x-ui.text-input name="form.password" label="Contraseña" type="password" wire:model="form.password" required autocomplete="current-password" />

        <label class="flex items-center gap-xs font-sans text-body-sm text-ink">
            <input wire:model="form.remember" type="checkbox" name="remember">
            Recordarme
        </label>

        <div class="flex items-center justify-between gap-sm pt-md border-t-[0.5px] border-hairline">
            @if (Route::has('password.request'))
                <a class="font-sans text-body-sm text-primary hover:underline" href="{{ route('password.request') }}" wire:navigate>
                    ¿Olvidaste tu contraseña?
                </a>
            @else
                <span></span>
            @endif

            <x-ui.button-primary type="submit">Iniciar sesión</x-ui.button-primary>
        </div>
    </form>

    <p class="mt-lg font-sans text-body-sm text-body">
        ¿No tienes cuenta?
        <a class="text-primary hover:underline" href="{{ route('register') }}" wire:navigate>Regístrate</a>
    </p>
</div>
