<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('tramite.index', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-ui.page-header title="Confirma tu contraseña">
        <x-slot:intro>Esta es un área segura. Confirma tu contraseña para continuar.</x-slot:intro>
    </x-ui.page-header>

    <form wire:submit="confirmPassword" class="space-y-md">
        <x-ui.error-summary />
        <x-ui.field id="password" label="Contraseña"><x-ui.input type="password" wire:model="password" autocomplete="current-password" /></x-ui.field>
        <div class="flex justify-end border-t border-hairline pt-lg">
            <x-ui.button-primary type="submit" wire:loading.attr="disabled" wire:target="confirmPassword">Confirmar</x-ui.button-primary>
        </div>
    </form>
</div>
