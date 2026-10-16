<?php

namespace App\Livewire\Cuenta;

use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Application\Tramite\EliminarCuenta;
use App\Livewire\Actions\Logout;
use App\Livewire\Concerns\NotificaToast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Presentación pura de "Mi perfil"; el borrado de cuenta vive en Application\Tramite\EliminarCuenta (ADR-014). */
#[Layout('layouts.tramite', ['cuenta' => true, 'title' => 'Mi perfil'])]
class Perfil extends Component
{
    use NotificaToast;

    public string $nombre = '';

    public string $contrasenaActual = '';

    public string $contrasenaNueva = '';

    public string $contrasenaNueva_confirmation = '';

    public function mount(): void
    {
        $this->nombre = Auth::user()->name;
    }

    public function guardarNombre(): void
    {
        $datos = $this->validate(
            ['nombre' => ['required', 'string', 'max:255']],
            ['nombre.required' => 'Escribe tu nombre.', 'nombre.max' => 'El nombre no puede pasar de 255 caracteres.'],
        );

        Auth::user()->update(['name' => $datos['nombre']]);

        $this->dispatch('profile-updated', name: $datos['nombre']);
        $this->toast('Nombre actualizado');
    }

    public function cambiarContrasena(): void
    {
        try {
            $datos = $this->validate([
                'contrasenaActual' => ['required', 'string', 'current_password'],
                'contrasenaNueva' => ['required', 'string', Password::defaults(), 'confirmed'],
            ], [
                'contrasenaActual.required' => 'Escribe tu contraseña actual.',
                'contrasenaActual.current_password' => 'La contraseña actual no es correcta.',
                'contrasenaNueva.required' => 'Escribe la nueva contraseña.',
                'contrasenaNueva.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            ]);
        } finally {
            $this->reset('contrasenaActual', 'contrasenaNueva', 'contrasenaNueva_confirmation');
        }

        Auth::user()->update(['password' => $datos['contrasenaNueva']]);

        $this->toast('Contraseña actualizada');
    }

    public function eliminarCuenta(Logout $cerrarSesion, EliminarCuenta $eliminarCuenta): void
    {
        try {
            $eliminarCuenta->ejecutar(Auth::id());
        } catch (PrecondicionIncumplida $e) {
            $this->addError('cuenta', $e->getMessage());
            $this->toast('No se pudo eliminar la cuenta', $e->getMessage(), 'error');

            return;
        }

        $cerrarSesion();
        $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.cuenta.perfil', ['correo' => Auth::user()->email, 'puedeEliminar' => ! RolesSedeq::cuentaProtegida(Auth::user())]);
    }
}
