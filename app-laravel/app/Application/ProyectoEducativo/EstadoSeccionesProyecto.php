<?php

namespace App\Application\ProyectoEducativo;

/**
 * Qué secciones del Proyecto Educativo están completas en un nivel, por slug.
 * Las usan el índice de secciones y el recorrido lateral; las secciones sin
 * formulario todavía salen en false.
 */
final class EstadoSeccionesProyecto
{
    public function __construct(
        private readonly PresentacionProyectoCapturada $presentacion,
        private readonly FilosofiaProyectoCapturada $filosofia,
        private readonly ObjetivosProyectoCapturada $objetivos,
        private readonly EnfoqueProyectoCapturada $enfoque,
        private readonly EvaluacionProyectoCapturada $evaluacion,
        private readonly PaecProyectoCapturada $paec,
        private readonly CurriculumProyectoCapturada $curriculum,
        private readonly IngresoProyectoCapturada $ingreso,
        private readonly EgresoProyectoCapturada $egreso,
        private readonly PerfilDocenteProyectoCapturada $perfilDocente,
        private readonly FormacionInicialProyectoCapturada $formacionInicial,
        private readonly FormacionContinuaProyectoCapturada $formacionContinua,
        private readonly AcademiasProyectoCapturada $academias,
    ) {}

    /** @return array<string, bool> slug => completa, en el orden del índice (las 20 secciones). */
    public function completas(int $escuelaNivelId): array
    {
        $hechas = [
            SeccionesProyectoEducativo::presentacion() => $this->presentacion->completa($escuelaNivelId),
            SeccionesProyectoEducativo::filosofia() => $this->filosofia->completa($escuelaNivelId),
            SeccionesProyectoEducativo::objetivos() => $this->objetivos->completa($escuelaNivelId),
            SeccionesProyectoEducativo::enfoque() => $this->enfoque->completa($escuelaNivelId),
            SeccionesProyectoEducativo::evaluacion() => $this->evaluacion->completa($escuelaNivelId),
            SeccionesProyectoEducativo::paec() => $this->paec->completa($escuelaNivelId),
            SeccionesProyectoEducativo::curriculum() => $this->curriculum->completa($escuelaNivelId),
            SeccionesProyectoEducativo::ingreso() => $this->ingreso->completa($escuelaNivelId),
            SeccionesProyectoEducativo::egreso() => $this->egreso->completa($escuelaNivelId),
            SeccionesProyectoEducativo::perfilDocente() => $this->perfilDocente->completa($escuelaNivelId),
            // La sección 11 son dos bloques: solo está completa con los dos guardados y completos.
            SeccionesProyectoEducativo::formacion() => $this->formacionInicial->completa($escuelaNivelId) && $this->formacionContinua->completa($escuelaNivelId),
            SeccionesProyectoEducativo::academias() => $this->academias->completa($escuelaNivelId),
        ];

        $completas = [];
        foreach (SeccionesProyectoEducativo::todas() as $slug => $_) {
            $completas[$slug] = $hechas[$slug] ?? false;
        }

        return $completas;
    }
}
