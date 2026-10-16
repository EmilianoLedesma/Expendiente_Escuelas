<?php

namespace App\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Models\Revisor;
use App\Models\User;
use App\Notifications\InvitacionRevisor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * El supervisor invita a un revisor. Correo nuevo: cuenta verificada con
 * contraseña aleatoria + enlace para definirla (sin evento Registered). Un
 * revisor revocado se reactiva (rol y revocado_at) y entra con su contraseña.
 * Cualquier otra cuenta existente se rechaza. El aviso sale después del commit.
 */
class InvitarRevisor
{
    private const OCUPADO = 'Este correo ya pertenece a otra cuenta del sistema.';

    /** @throws DatosInvalidos|PrecondicionIncumplida */
    public function ejecutar(int $actorId, string $nombre, string $correo): User
    {
        $nombre = trim($nombre);
        $correo = mb_strtolower(trim($correo));

        $validador = Validator::make(
            ['nombre' => $nombre, 'correo' => $correo],
            ['nombre' => ['required', 'string', 'max:255'], 'correo' => ['required', 'email', 'max:255']],
            ['nombre.required' => 'Indica el nombre del revisor.', 'correo.required' => 'Indica el correo del revisor.', 'correo.email' => 'Escribe un correo válido.'],
        );
        if ($validador->fails()) {
            throw new DatosInvalidos(collect($validador->errors()->toArray())->map(fn (array $m) => $m[0])->all());
        }

        return DB::transaction(function () use ($actorId, $nombre, $correo) {
            $actor = User::findOrFail($actorId);
            RolesSedeq::exigir($actor, RolesSedeq::SUPERVISOR);

            $usuario = User::where('email', $correo)->lockForUpdate()->first();
            $conEnlace = false;

            if ($usuario !== null) {
                // Solo un revisor revocado puede volver a invitarse.
                // Tampoco si ya tiene otro rol o es solicitante: nunca se apila un rol sobre otra cuenta.
                if (! Revisor::where('user_id', $usuario->id)->whereNotNull('revocado_at')->exists()
                    || $usuario->getRoleNames()->isNotEmpty() || $usuario->solicitante()->exists()) {
                    throw new DatosInvalidos(['correo' => self::OCUPADO]);
                }
                Revisor::whereKey($usuario->id)->update(['revocado_at' => null]);
            } else {
                try {
                    $usuario = User::create(['name' => $nombre, 'email' => $correo, 'password' => Str::random(40)]);
                } catch (UniqueConstraintViolationException) {
                    // Otra invitación concurrente creó el mismo correo entre el SELECT y el INSERT.
                    throw new DatosInvalidos(['correo' => self::OCUPADO]);
                }
                // El enlace llega a ese correo: abrirlo ya prueba que es suyo.
                $usuario->forceFill(['email_verified_at' => now()])->save();
                Revisor::create(['user_id' => $usuario->id]);
                $conEnlace = true;
            }
            $usuario->assignRole(Role::findOrCreate(RolesSedeq::REVISOR, 'web'));

            // Si el aviso falla, la cuenta ya está guardada: se reporta, no se convierte en error.
            DB::afterCommit(fn () => rescue(fn () => $usuario->notify(new InvitacionRevisor(
                $actor->name,
                $conEnlace ? Password::createToken($usuario) : null,
            )), report: true));

            return $usuario;
        });
    }
}
