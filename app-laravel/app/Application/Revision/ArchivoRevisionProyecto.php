<?php

namespace App\Application\Revision;

use App\Application\Excepciones\DatosInvalidos;
use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use App\Models\ProyectoEducativoCalendarioAcademico;
use App\Models\ProyectoEducativoFormacionPolitica;
use App\Models\ProyectoEducativoMapaCurricular;
use App\Models\ProyectoEducativoPaec;
use App\Models\ProyectoEducativoPlaneacionAcademica;
use App\Models\ProyectoEducativoProgramasEstudios;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

/**
 * Los PDF que el solicitante subió al proyecto educativo, para quien lo revisa (evaluador y supervisor:
 * AccesoSedeq::puedeVerProyecto; el revisor nunca). La ruta de disco solo la ve el controlador de descarga, que sirve el
 * archivo; la pantalla recibe claves y etiquetas (ver `disponibles`).
 */
final class ArchivoRevisionProyecto
{
    public function __construct(private readonly AccesoSedeq $acceso) {}

    /**
     * clave => [sección, etiqueta, modelo, columna de la ruta, filtro extra].
     *
     * @return array<string, array{0: string, 1: string, 2: class-string<Model>, 3: string, 4: array<string, string>}>
     */
    private function fuentes(): array
    {
        return [
            'paec_hoja_ruta' => [SeccionesProyectoEducativo::paec(), 'Hoja de ruta del PAEC', ProyectoEducativoPaec::class, 'hoja_ruta_path', []],
            'paec_planeacion' => [SeccionesProyectoEducativo::paec(), 'Planeación del PAEC', ProyectoEducativoPaec::class, 'planeacion_path', []],
            'formacion_planeacion' => [SeccionesProyectoEducativo::formacion(), 'Planeación de formación continua', ProyectoEducativoFormacionPolitica::class, 'planeacion_path', ['tipo' => 'continua']],
            'mapa_curricular' => [SeccionesProyectoEducativo::mapaCurricular(), 'Mapa curricular', ProyectoEducativoMapaCurricular::class, 'archivo_path', []],
            'programas_estudios' => [SeccionesProyectoEducativo::programasEstudios(), 'Programas de estudios', ProyectoEducativoProgramasEstudios::class, 'archivo_path', []],
            'planeacion_academica' => [SeccionesProyectoEducativo::planeacionAcademica(), 'Planeación académica', ProyectoEducativoPlaneacionAcademica::class, 'archivo_path', []],
            'calendario_academico' => [SeccionesProyectoEducativo::calendarioAcademico(), 'Calendario académico', ProyectoEducativoCalendarioAcademico::class, 'archivo_path', []],
        ];
    }

    /**
     * Ruta de disco del PDF, o null si el solicitante aún no lo subió.
     *
     * @throws AuthorizationException
     * @throws DatosInvalidos si la clave no es de un archivo del proyecto.
     */
    public function ruta(int $actorId, int $escuelaNivelId, string $clave): ?string
    {
        $this->autorizar($actorId, $escuelaNivelId);
        $fuente = $this->fuentes()[$clave] ?? throw new DatosInvalidos(['archivo' => 'Ese archivo no existe.']);

        return $this->guardada($fuente, $escuelaNivelId);
    }

    /**
     * Archivos ya subidos de una sección, sin rutas: lo que la pantalla ofrece para descargar.
     *
     * @return list<array{clave: string, etiqueta: string}>
     *
     * @throws AuthorizationException
     */
    public function disponibles(int $actorId, int $escuelaNivelId, string $slug): array
    {
        $this->autorizar($actorId, $escuelaNivelId);

        $salida = [];
        foreach ($this->fuentes() as $clave => $fuente) {
            if ($fuente[0] === $slug && $this->guardada($fuente, $escuelaNivelId) !== null) {
                $salida[] = ['clave' => $clave, 'etiqueta' => $fuente[1]];
            }
        }

        return $salida;
    }

    /** @param array{0: string, 1: string, 2: class-string<Model>, 3: string, 4: array<string, string>} $fuente */
    private function guardada(array $fuente, int $escuelaNivelId): ?string
    {
        $ruta = $fuente[2]::where(['escuela_nivel_id' => $escuelaNivelId, ...$fuente[4]])->value($fuente[3]);

        return is_string($ruta) && $ruta !== '' ? $ruta : null;
    }

    private function autorizar(int $actorId, int $escuelaNivelId): void
    {
        if (! $this->acceso->puedeVerProyecto(User::findOrFail($actorId), $escuelaNivelId)) {
            throw new AuthorizationException('No puedes revisar este proyecto educativo.');
        }
    }
}
