<?php

namespace Tests\Unit\Domain\Calendario;

use App\Domain\Calendario\DiasHabiles;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DiasHabilesTest extends TestCase
{
    public function test_cinco_dias_habiles_desde_un_viernes_caen_en_el_viernes_siguiente(): void
    {
        $this->assertSame('2026-11-27', DiasHabiles::sumar(new DateTimeImmutable('2026-11-20'), 5)->format('Y-m-d'));
    }

    public function test_un_dia_habil_desde_sabado_es_lunes(): void
    {
        $this->assertSame('2026-11-23', DiasHabiles::sumar(new DateTimeImmutable('2026-11-21'), 1)->format('Y-m-d'));
    }

    public function test_cero_dias_devuelve_la_misma_fecha(): void
    {
        $this->assertSame('2026-11-21', DiasHabiles::sumar(new DateTimeImmutable('2026-11-21'), 0)->format('Y-m-d'));
    }
}
