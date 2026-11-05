<?php

namespace App\Livewire\Cuenta;

use App\Application\Archivo\ArchivarCuenta;
use App\Application\Auth\CerrarOtrasSesiones;
use App\Application\Captura\ReglasCaptura;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\RolesSedeq;
use App\Livewire\Actions\Logout;
use App\Livewire\Concerns\NotificaToast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Presentación pura de "Mi perfil"; el archivo de cuenta vive en Application\Archivo\ArchivarCuenta (ADR-014). */
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
            ['nombre' => ReglasCaptura::nombreVisible()],
            ['nombre.required' => 'Escribe tu nombre.', 'nombre.max' => 'El nombre no puede pasar de 255 caracteres.'],
        );

        Auth::user()->update(['name' => $datos['nombre']]);

        $this->dispatch('profile-updated', name: $datos['nombre']);
        $this->toast('Nombre actualizado');
    }

    /** true = la contraseña cambió; un dato inválido relanza la excepción, que x-ui.stateful-button muestra como error. */
    public function cambiarContrasena(): bool
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
        } catch (ValidationException $e) {
            $this->toastDatosInvalidos();

            throw $e;
        } finally {
            $this->reset('contrasenaActual', 'contrasenaNueva', 'contrasenaNueva_confirmation');
        }

        Auth::user()->forceFill(['password' => $datos['contrasenaNueva'], 'remember_token' => Str::random(60)])->save();
        // Los demás equipos con la cuenta abierta (o con "recordarme") dejan de entrar; este sigue.
        app(CerrarOtrasSesiones::class)->ejecutar((int) Auth::id(), session()->getId());

        $this->toast('Contraseña actualizada', 'Úsala la próxima vez que inicies sesión.');

        return true;
    }

    public function archivarCuenta(Logout $cerrarSesion, ArchivarCuenta $archivarCuenta): void
    {
        try {
            $archivarCuenta->ejecutar((int) Auth::id(), (int) Auth::id());
        } catch (PrecondicionIncumplida $e) {
            $this->addError('cuenta', $e->getMessage());
            $this->toast('No se pudo archivar la cuenta', $e->getMessage(), 'error');

            return;
        }

        $cerrarSesion();
        session()->flash('aviso_login', 'Tu cuenta se archivó. Para recuperarla, contacta a un administrador.');
        $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.cuenta.perfil', ['correo' => Auth::user()->email, 'puedeEliminar' => ! RolesSedeq::cuentaProtegida(Auth::user())]);
    }
}
