<?php

namespace App\Application\ResponsablesNivel;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\Tramite\EliminarTramite;
use App\Models\EscuelaNivel;
use App\Models\ResponsableNivel;
use App\Models\User;
use App\Notifications\InvitacionResponsableNivel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * El dueño de un nivel en captura asigna a un responsable (spec 2026-10-07 §3).
 * Correo nuevo: cuenta con contraseña aleatoria + enlace para definirla.
 * Correo de un responsable existente: solo se le agrega el nivel. Cualquier
 * otro usuario (solicitante, SEDEQ) se rechaza. El aviso sale después del commit.
 */
class InvitarResponsableNivel
{
    public const ROL = 'responsable_nivel';

    /** @throws DatosInvalidos */
    public function ejecutar(int $solicitanteId, int $escuelaNivelId, string $nombre, string $correo): ResponsableNivel
    {
        $nombre = trim($nombre);
        $correo = mb_strtolower(trim($correo));

        $validador = Validator::make(
            ['nombre' => $nombre, 'correo' => $correo],
            ['nombre' => ['required', 'string', 'max:255'], 'correo' => ['required', 'email', 'max:255']],
            ['nombre.required' => 'Indica el nombre del responsable.', 'correo.required' => 'Indica el correo del responsable.', 'correo.email' => 'Escribe un correo válido.'],
        );
        if ($validador->fails()) {
            throw new DatosInvalidos(collect($validador->errors()->toArray())->map(fn (array $m) => $m[0])->all());
        }

        return DB::transaction(function () use ($solicitanteId, $escuelaNivelId, $nombre, $correo) {
            $nivel = EscuelaNivel::with(['escuela', 'nivelEducativo'])->lockForUpdate()->find($escuelaNivelId);

            if ($nivel === null || (int) $nivel->escuela->solicitante_id !== $solicitanteId) {
                throw new DatosInvalidos(['escuelaNivelId' => 'Elige un nivel de tus trámites.']);
            }
            if ((int) $nivel->estado_id !== EliminarTramite::idEnCaptura()) {
                throw new DatosInvalidos(['escuelaNivelId' => 'Solo puedes asignar responsables a niveles en captura.']);
            }

            $usuario = User::where('email', $correo)->lockForUpdate()->first();
            $esNueva = false;

            if ($usuario !== null) {
                if (! $usuario->hasRole(self::ROL) || $usuario->solicitante()->exists()) {
                    throw new DatosInvalidos(['correo' => 'Este correo ya pertenece a otra cuenta del sistema.']);
                }
                if (ResponsableNivel::where('user_id', $usuario->id)->where('escuela_nivel_id', $nivel->id)->exists()) {
                    throw new DatosInvalidos(['correo' => 'Esta persona ya es responsable de este nivel.']);
                }
            } else {
                try {
                    $usuario = User::create(['name' => $nombre, 'email' => $correo, 'password' => Str::random(40)]);
                } catch (UniqueConstraintViolationException) {
                    // Otra invitación concurrente creó el mismo correo entre el SELECT y el INSERT.
                    throw new DatosInvalidos(['correo' => 'Este correo ya pertenece a otra cuenta del sistema.']);
                }
                // El enlace de activación llega a ese correo: abrirlo ya prueba que es suyo.
                $usuario->forceFill(['email_verified_at' => now()])->save();
                $usuario->assignRole(Role::findOrCreate(self::ROL, 'web'));
                $esNueva = true;
            }

            $acceso = ResponsableNivel::create([
                'user_id' => $usuario->id,
                'escuela_nivel_id' => $nivel->id,
                'invitado_por_solicitante_id' => $solicitanteId,
            ]);

            $quien = User::whereHas('solicitante', fn ($q) => $q->whereKey($solicitanteId))->firstOrFail()->name;
            // Si el aviso falla, el acceso ya está guardado: se reporta, no se convierte en error.
            DB::afterCommit(fn () => rescue(fn () => $usuario->notify(new InvitacionResponsableNivel(
                $quien,
                $nivel->nivelEducativo->nombre,
                $esNueva ? Password::broker()->createToken($usuario) : null,
            )), report: true));

            return $acceso;
        });
    }
}
