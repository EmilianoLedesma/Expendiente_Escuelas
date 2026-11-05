<?php

namespace App\Application\Auditoria;

use App\Application\Archivo\RegistrarAccion;

/** Texto en palabras para las filas de bitacora_acciones; una sola fuente para la bitácora y el historial por trámite. */
final class EtiquetasBitacora
{
    public const ACCIONES = [
        RegistrarAccion::ARCHIVAR_TRAMITE => 'Archivó el trámite',
        RegistrarAccion::RESTAURAR_TRAMITE => 'Restauró el trámite',
        RegistrarAccion::PURGAR_TRAMITE => 'Eliminó definitivamente el trámite',
        RegistrarAccion::ARCHIVAR_CUENTA => 'Archivó la cuenta',
        RegistrarAccion::RESTAURAR_CUENTA => 'Restauró la cuenta',
        RegistrarAccion::PURGAR_CUENTA => 'Eliminó definitivamente la cuenta',
        RegistrarAccion::ARCHIVAR_PLANTEL => 'Archivó el plantel',
        RegistrarAccion::RESTAURAR_PLANTEL => 'Restauró el plantel',
        RegistrarAccion::PROCESAR_COLA => 'Procesó la cola de correos',
        RegistrarAccion::REINTENTAR_FALLIDOS => 'Reintentó trabajos fallidos',
        RegistrarAccion::DESCARTAR_FALLIDOS => 'Descartó trabajos fallidos',
        RegistrarAccion::CORREO_PRUEBA => 'Envió un correo de prueba',
        RegistrarAccion::CAMBIAR_ROL => 'Cambió el rol de una cuenta',
        RegistrarAccion::CREAR_CUENTA_SEDEQ => 'Creó una cuenta de personal',
    ];

    public const OBJETOS = ['escuela' => 'Trámite', 'user' => 'Cuenta', 'plantel' => 'Plantel', 'sistema' => 'Sistema'];

    public static function accion(string $accion): string
    {
        return self::ACCIONES[$accion] ?? $accion;
    }

    public static function objeto(string $tipo, int $id): string
    {
        if ($tipo === 'sistema') {
            return 'Sistema';
        }

        return (self::OBJETOS[$tipo] ?? $tipo).' '.$id;
    }

    /** @param array<string, mixed> $detalle */
    public static function resumen(array $detalle): string
    {
        $partes = [];
        foreach ($detalle as $clave => $valor) {
            if ($clave === 'lote' || ! is_scalar($valor)) {
                continue;
            }
            $partes[] = str_replace('_', ' ', (string) $clave).': '.$valor;
        }

        return implode(' · ', $partes);
    }
}
