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
            : route('tramite.index', absolute: false);

        $this->redirectIntended(default: $default, navigate: true);
    }
}; ?>

<div>
    <x-ui.page-header title="Iniciar sesión">
        <x-slot:intro>Accede a tu cuenta para continuar con tus trámites.</x-slot:intro>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tipo="success" class="mb-lg">{{ session('status') }}</x-ui.alert>
    @endif

    <form wire:submit="login" class="space-y-md">
        <x-ui.error-summary />

        <x-ui.field id="form.email" label="Correo electrónico">
            <x-ui.input type="email" wire:model="form.email" autofocus autocomplete="username" />
        </x-ui.field>
        <x-ui.field id="form.password" label="Contraseña">
            <x-ui.input type="password" wire:model="form.password" autocomplete="current-password" />
        </x-ui.field>
        <x-ui.checkbox id="form.remember" wire:model="form.remember" name="remember">Recordarme</x-ui.checkbox>

        <div class="flex flex-col-reverse gap-md border-t border-hairline pt-lg sm:flex-row sm:items-center sm:justify-between">
            @if (Route::has('password.request'))
                <a class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline" href="{{ route('password.request') }}" wire:navigate>¿Olvidaste tu contraseña?</a>
            @else
                <span></span>
            @endif
            <x-ui.button-primary type="submit" wire:loading.attr="disabled" wire:target="login">Iniciar sesión</x-ui.button-primary>
        </div>
    </form>

    <p class="mt-lg text-body-md text-body">
        ¿No tienes cuenta?
        <a class="font-semibold text-primary underline underline-offset-4 hover:no-underline" href="{{ route('register') }}" wire:navigate>Regístrate</a>
    </p>
</div>
