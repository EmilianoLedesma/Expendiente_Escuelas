<?php

namespace App\Filament\Resources\Usuarios\Pages;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Revision\CrearCuentaSedeq;
use App\Filament\Resources\Usuarios\UsuarioResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CrearUsuario extends CreateRecord
{
    protected static string $resource = UsuarioResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Crear cuenta de SEDEQ';
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CrearCuentaSedeq::class)->ejecutar((int) auth()->id(), $data['nombre'], $data['correo'], $data['rol']);
        } catch (DatosInvalidos $e) {
            // Las claves de la acción (nombre, correo, rol) coinciden con los campos del formulario.
            throw ValidationException::withMessages(collect($e->errores)->mapWithKeys(fn ($m, $k) => ["data.$k" => $m])->all());
        } catch (PrecondicionIncumplida $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cuenta creada. Se envió un enlace para definir la contraseña.';
    }
}
