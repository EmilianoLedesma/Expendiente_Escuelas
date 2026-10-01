<?php

namespace App\Application\Personal\DTO;

/** One Anexo 1 row (COMPENDIO §Paso 3: nombre, nacionalidad, sexo, estudios, cédula o documento, cargo). */
final readonly class DatosPersona
{
    public function __construct(
        public ?int $cargoPuestoId,
        public string $nombre,
        public string $nacionalidad,
        public string $sexo,
        public string $estudios,
        public string $cedulaODocumento,
        // Inicial: responsable/asistente de sala (cargos_puestos.requiere_sala)
        public ?int $salaId = null,
        // Secundaria: docente titular por asignatura (cargos_puestos.requiere_asignatura)
        public ?int $asignaturaId = null,
    ) {}
}
