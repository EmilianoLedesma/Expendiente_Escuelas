<?php

namespace Tests\Feature\Application\Auditoria;

use App\Application\Archivo\RegistrarAccion;
use App\Application\Auditoria\EtiquetasBitacora;
use PHPUnit\Framework\TestCase;

class EtiquetasBitacoraTest extends TestCase
{
    public function test_toda_accion_registrable_tiene_etiqueta_propia(): void
    {
        $acciones = (new \ReflectionClass(RegistrarAccion::class))->getConstants();

        foreach ($acciones as $accion) {
            $this->assertArrayHasKey($accion, EtiquetasBitacora::ACCIONES, "Falta etiqueta para {$accion}");
        }
    }

    public function test_accion_desconocida_se_muestra_tal_cual(): void
    {
        $this->assertSame('algo_raro', EtiquetasBitacora::accion('algo_raro'));
        $this->assertSame('Archivó el trámite', EtiquetasBitacora::accion('archivar_tramite'));
    }

    public function test_objeto_en_palabras(): void
    {
        $this->assertSame('Trámite 4', EtiquetasBitacora::objeto('escuela', 4));
        $this->assertSame('Cuenta 7', EtiquetasBitacora::objeto('user', 7));
        $this->assertSame('Plantel 3', EtiquetasBitacora::objeto('plantel', 3));
        $this->assertSame('Sistema', EtiquetasBitacora::objeto('sistema', 0));
    }

    public function test_resumen_omite_el_lote_y_lo_no_escalar(): void
    {
        $resumen = EtiquetasBitacora::resumen(['lote' => 'abc', 'tramites' => 2, 'asignaciones_liberadas' => 1, 'lista' => [1, 2]]);

        $this->assertSame('tramites: 2 · asignaciones liberadas: 1', $resumen);
        $this->assertSame('', EtiquetasBitacora::resumen([]));
    }
}
