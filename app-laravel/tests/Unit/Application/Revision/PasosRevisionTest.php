<?php

namespace Tests\Unit\Application\Revision;

use App\Application\Revision\PasosRevision;
use App\Application\Tramite\ResumenTramite;
use PHPUnit\Framework\TestCase;

class PasosRevisionTest extends TestCase
{
    public function test_inicial_tiene_11_y_los_demas_10(): void
    {
        $this->assertCount(11, PasosRevision::TODOS);
        $this->assertCount(11, PasosRevision::paraNivel('inicial'));
        $this->assertCount(10, PasosRevision::paraNivel('primaria'));
        $this->assertNotContains('mobiliario', PasosRevision::paraNivel('primaria'));
        $this->assertSame(['plantel', 'responsable', 'documentos', 'niveles', 'documentos_nivel'], array_slice(PasosRevision::paraNivel('inicial'), 0, 5));
    }

    public function test_titulos_vienen_de_resumen_tramite(): void
    {
        $this->assertSame(ResumenTramite::tituloSeccion('infraestructura'), PasosRevision::titulo('infraestructura'));
        $this->assertSame('Datos del plantel', PasosRevision::titulo('plantel'));
    }

    public function test_titulo_de_clave_desconocida_lanza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResumenTramite::tituloSeccion('nada');
    }
}
