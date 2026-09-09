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

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Crear cuenta</h1>
    <p class="font-sans text-body-sm text-body mb-lg">
        Regístrate para iniciar y dar seguimiento a tus trámites de incorporación.
    </p>

    <form wire:submit="register" class="space-y-md">
        <x-ui.text-input name="name" label="Nombre completo" wire:model="name" required autofocus autocomplete="name" />

        <x-ui.text-input name="email" label="Correo electrónico" type="email" wire:model="email" required autocomplete="username" />

        <x-ui.text-input name="password" label="Contraseña" type="password" wire:model="password" required autocomplete="new-password" />

        <x-ui.text-input name="password_confirmation" label="Confirmar contraseña" type="password" wire:model="password_confirmation" required autocomplete="new-password" />

        <div class="flex items-center justify-between gap-sm pt-md border-t-[0.5px] border-hairline">
            <a class="font-sans text-body-sm text-primary hover:underline" href="{{ route('login') }}" wire:navigate>
                ¿Ya tienes cuenta?
            </a>

            <x-ui.button-primary type="submit">Crear cuenta</x-ui.button-primary>
        </div>
    </form>
</div>
