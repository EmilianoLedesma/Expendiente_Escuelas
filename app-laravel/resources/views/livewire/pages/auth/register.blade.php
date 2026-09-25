<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('tramite.index', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-ui.page-header title="Crear cuenta">
        <x-slot:intro>Regístrate para iniciar y dar seguimiento a tus trámites de incorporación.</x-slot:intro>
    </x-ui.page-header>

    <form wire:submit="register" class="space-y-md">
        <x-ui.error-summary />

        <x-ui.field id="name" label="Nombre completo"><x-ui.input wire:model="name" autofocus autocomplete="name" /></x-ui.field>
        <x-ui.field id="email" label="Correo electrónico"><x-ui.input type="email" wire:model="email" autocomplete="username" /></x-ui.field>
        <x-ui.field id="password" label="Contraseña"><x-ui.input type="password" wire:model="password" autocomplete="new-password" /></x-ui.field>
        <x-ui.field id="password_confirmation" label="Confirmar contraseña"><x-ui.input type="password" wire:model="password_confirmation" autocomplete="new-password" /></x-ui.field>

        <div class="flex flex-col-reverse gap-md border-t border-hairline pt-lg sm:flex-row sm:items-center sm:justify-between">
            <a class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline" href="{{ route('login') }}" wire:navigate>¿Ya tienes cuenta?</a>
            <x-ui.button-primary type="submit" wire:loading.attr="disabled" wire:target="register">Crear cuenta</x-ui.button-primary>
        </div>
    </form>
</div>
