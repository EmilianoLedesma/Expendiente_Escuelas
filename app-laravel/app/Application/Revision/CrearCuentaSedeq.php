<?php

namespace App\Application\Revision;

use App\Application\Archivo\RegistrarAccion;
use App\Application\Captura\ReglasCaptura;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * El admin crea una cuenta de SEDEQ (admin o supervisor; los revisores se invitan
 * aparte). Cuenta verificada con contraseña aleatoria y enlace para definirla,
 * enviado tras el commit. Un correo ya registrado (incluso de una cuenta archivada)
 * se rechaza. La bitácora guarda solo ids y rol, nunca nombre ni correo.
 */
class CrearCuentaSedeq
{
    private const OCUPADO = 'Este correo ya pertenece a otra cuenta del sistema.';

    public function __construct(private readonly RegistrarAccion $registrar) {}

    /** @throws DatosInvalidos|PrecondicionIncumplida */
    public function ejecutar(int $actorId, string $nombre, string $correo, string $rol): User
    {
        // Permiso primero: un no-admin nunca recibe feedback de campos. Se reconfirma bajo bloqueo en la transacción.
        $actorPrevio = User::find($actorId);
        if ($actorPrevio === null || ! $actorPrevio->hasRole(RolesSedeq::ADMIN)) {
            throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
        }

        $nombre = trim($nombre);
        $correo = mb_strtolower(trim($correo));

        $validador = Validator::make(
            ['nombre' => $nombre, 'correo' => $correo, 'rol' => $rol],
            [
                'nombre' => ReglasCaptura::nombreVisible(),
                'correo' => ['required', 'email', 'max:255'],
                'rol' => ['required', Rule::in([RolesSedeq::ADMIN, RolesSedeq::SUPERVISOR])],
            ],
            [
                'nombre.required' => 'Indica el nombre de la cuenta.',
                'correo.required' => 'Indica el correo de la cuenta.',
                'correo.email' => 'Escribe un correo válido.',
                'rol.required' => 'Elige un rol.',
                'rol.in' => 'El rol elegido no existe.',
            ],
        );
        if ($validador->fails()) {
            throw new DatosInvalidos(collect($validador->errors()->toArray())->map(fn (array $m) => $m[0])->all());
        }

        return DB::transaction(function () use ($actorId, $nombre, $correo, $rol) {
            $actor = User::whereKey($actorId)->lockForUpdate()->first()
                ?? throw new PrecondicionIncumplida('rol', 'No tienes permiso para esta acción.');
            RolesSedeq::exigir($actor, RolesSedeq::ADMIN);

            if (User::whereRaw('lower(email) = ?', [$correo])->exists()) {
                throw new DatosInvalidos(['correo' => self::OCUPADO]);
            }
            try {
                $usuario = User::create(['name' => $nombre, 'email' => $correo, 'password' => Str::random(40)]);
            } catch (UniqueConstraintViolationException) {
                throw new DatosInvalidos(['correo' => self::OCUPADO]);
            }
            // El enlace llega a ese correo: abrirlo ya prueba que es suyo.
            $usuario->forceFill(['email_verified_at' => now()])->save();
            $usuario->assignRole(Role::findOrCreate($rol, 'web'));

            $this->registrar->ejecutar($actorId, RegistrarAccion::CREAR_CUENTA_SEDEQ, 'user', $usuario->id, null, ['rol' => $rol]);

            // Si el aviso falla, la cuenta ya está guardada: se reporta, no se convierte en error.
            DB::afterCommit(fn () => rescue(fn () => $usuario->sendPasswordResetNotification(Password::createToken($usuario)), report: true));

            return $usuario;
        });
    }
}
