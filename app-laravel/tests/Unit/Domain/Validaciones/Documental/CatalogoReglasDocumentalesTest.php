<?php

namespace Tests\Unit\Domain\Validaciones\Documental;

use App\Domain\Validaciones\Documental\CatalogoReglasDocumentales;
use App\Domain\Validaciones\Documental\ContextoValidacion;
use App\Domain\Validaciones\Documental\ReglaDocumental;
use PHPUnit\Framework\TestCase;

class CatalogoReglasDocumentalesTest extends TestCase
{
    public function test_define_las_seis_reglas_en_orden_con_claves_unicas(): void
    {
        $contexto = new ContextoValidacion('fisica', [], [], []);
        $claves = array_map(fn (ReglaDocumental $r) => $r->evaluar($contexto)->clave, CatalogoReglasDocumentales::reglas());

        $this->assertSame([
            'documentos_requeridos_presentes',
            'nombre_identidad_coincide',
            'curp_coincide',
            'nombre_fiscal_coincide',
            'rfc_coincide',
            'domicilio_coincide',
        ], $claves);
    }
}
