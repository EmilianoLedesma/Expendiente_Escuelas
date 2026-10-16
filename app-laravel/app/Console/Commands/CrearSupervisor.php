<?php

namespace App\Console\Commands;

use App\Application\Revision\RolesSedeq;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

#[Signature('sedeq:crear-supervisor {correo} {nombre}')]
#[Description('Crea el primer supervisor SEDEQ y le envía el enlace para definir su contraseña')]
class CrearSupervisor extends Command
{
    public function handle(): int
    {
        $correo = mb_strtolower(trim((string) $this->argument('correo')));

        if (User::where('email', $correo)->exists()) {
            $this->error('Ya existe una cuenta con ese correo.');

            return self::FAILURE;
        }

        $usuario = DB::transaction(function () use ($correo) {
            $usuario = User::create(['name' => trim((string) $this->argument('nombre')), 'email' => $correo, 'password' => Str::random(40)]);
            $usuario->forceFill(['email_verified_at' => now()])->save();
            $usuario->assignRole(Role::findOrCreate(RolesSedeq::SUPERVISOR, 'web'));

            return $usuario;
        });

        $usuario->sendPasswordResetNotification(Password::createToken($usuario));
        $this->info("Supervisor creado. Se envió el enlace para definir contraseña a {$correo}.");

        return self::SUCCESS;
    }
}
