<?php

namespace App\Application\ProyectoEducativo\DTO;

/**
 * Sección 1 del Proyecto Educativo. Las propiedades se llaman igual que las columnas (y que las propiedades
 * del componente Livewire) para que una clave de error sea la misma en todas las capas. Todo es opcional:
 * la sección se guarda como borrador. Fechas en 'Y-m-d'; teléfonos tal como se tecleen (el caso de uso los normaliza).
 */
final readonly class DatosPresentacionProyecto
{
    public const CAMPOS = [
        'fecha_entrega', 'nombre_institucion', 'fecha_apertura', 'domicilio_completo', 'representante_legal',
        'correo', 'telefono', 'celular', 'pagina_web', 'modalidad', 'ciclo_escolar', 'ciclo_escolar_otro',
        'duracion', 'duracion_otro', 'sintesis_factibilidad',
    ];

    public function __construct(
        public ?string $fecha_entrega = null,
        public ?string $nombre_institucion = null,
        public ?string $fecha_apertura = null,
        public ?string $domicilio_completo = null,
        public ?string $representante_legal = null,
        public ?string $correo = null,
        public ?string $telefono = null,
        public ?string $celular = null,
        public ?string $pagina_web = null,
        public ?string $modalidad = null,
        public ?string $ciclo_escolar = null,
        public ?string $ciclo_escolar_otro = null,
        public ?string $duracion = null,
        public ?string $duracion_otro = null,
        public ?string $sintesis_factibilidad = null,
    ) {}

    /** @return array<string, string|null> */
    public function aArray(): array
    {
        return get_object_vars($this);
    }
}
