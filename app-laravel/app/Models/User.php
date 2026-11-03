<?php

namespace App\Models;

use App\Application\Revision\RolesSedeq;
use App\Notifications\RestablecerContrasena;
use App\Notifications\VerificarCorreo;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property Carbon|null $archivado_at
 * @property string|null $archivado_lote
 * @property int|null $plantel_principal_id calculado solo en UsuarioResource (subconsulta)
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'archivado_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasOne<Solicitante, $this> */
    public function solicitante(): HasOne
    {
        return $this->hasOne(Solicitante::class);
    }

    public function accesosNivel(): HasMany
    {
        return $this->hasMany(ResponsableNivel::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(RolesSedeq::ADMIN);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerificarCorreo);
    }
}
