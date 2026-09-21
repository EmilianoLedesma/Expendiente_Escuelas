<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $calle
 * @property string|null $numero_ext
 * @property string|null $numero_int
 * @property string $colonia
 * @property string|null $localidad
 * @property string $municipio
 * @property string $codigo_postal
 * @property string|null $telefono
 * @property string|null $correo_electronico
 * @property string|null $metros_totales
 * @property string|null $metros_construidos
 * @property string|null $colindancia_norte
 * @property string|null $colindancia_sur
 * @property string|null $colindancia_este
 * @property string|null $colindancia_oeste
 * @property string|null $latitud
 * @property string|null $longitud
 * @property string|null $area_civica_m2
 * @property bool|null $tiene_asta_bandera
 */
class Plantel extends Model
{
    protected $table = 'planteles';

    protected $fillable = [
        'calle',
        'numero_ext',
        'numero_int',
        'colonia',
        'localidad',
        'municipio',
        'codigo_postal',
        'telefono',
        'correo_electronico',
        'metros_totales',
        'metros_construidos',
        'colindancia_norte',
        'colindancia_sur',
        'colindancia_este',
        'colindancia_oeste',
        'latitud',
        'longitud',
        'area_civica_m2',
        'tiene_asta_bandera',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoPlantel::class);
    }

    public function escuelas(): HasMany
    {
        return $this->hasMany(Escuela::class);
    }

    public function serviciosCercanos(): HasMany
    {
        return $this->hasMany(ServicioCercano::class);
    }

    public function estudiosActuales(): HasMany
    {
        return $this->hasMany(InmuebleEstudioActual::class);
    }
}
