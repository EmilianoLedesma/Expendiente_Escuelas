<?php

use App\Application\Captura\Normalizacion;
use App\Application\Captura\Normalizar;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    #[Normalizar(Normalizacion::Correo)]
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <x-ui.page-header title="Restablecer contraseña">
        <x-slot:intro>Escribe tu nueva contraseña.</x-slot:intro>
    </x-ui.page-header>

    <form novalidate wire:submit="resetPassword" class="space-y-md">
        <x-ui.error-summary />
        <x-ui.field id="email" label="Correo electrónico"><x-ui.input type="email" wire:model.blur="email" autofocus autocomplete="username" /></x-ui.field>
        <x-ui.field id="password" label="Nueva contraseña"><x-ui.input type="password" wire:model.blur="password" autocomplete="new-password" /></x-ui.field>
        <x-ui.field id="password_confirmation" label="Confirmar contraseña"><x-ui.input type="password" wire:model.blur="password_confirmation" autocomplete="new-password" /></x-ui.field>
        <div class="flex justify-end border-t border-hairline pt-lg">
            <x-ui.button-primary type="submit" wire:loading.attr="disabled" wire:target="resetPassword">Restablecer contraseña</x-ui.button-primary>
        </div>
    </form>
</div>
