<?php

namespace Tests\Feature\Application\ProyectoEducativo;

use App\Application\ProyectoEducativo\SeccionesProyectoEducativo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SeccionesProyectoEducativoTest extends TestCase
{
    public function test_son_veinte_en_el_orden_del_indice(): void
    {
        $nombres = array_values(SeccionesProyectoEducativo::todas());

        $this->assertCount(20, $nombres);
        $this->assertSame('Presentación del Proyecto Académico', $nombres[0]);
        $this->assertSame('Programa Aula, Escuela y Comunidad (PAEC)', $nombres[5]);
        $this->assertSame('Bibliografía del Proyecto', $nombres[19]);
    }

    public function test_los_slugs_son_unicos_y_seguros_para_url(): void
    {
        $slugs = array_keys(SeccionesProyectoEducativo::todas());

        $this->assertCount(20, array_unique($slugs));
        foreach ($slugs as $slug) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', (string) $slug);
            $this->assertTrue(SeccionesProyectoEducativo::existe((string) $slug));
        }
        $this->assertFalse(SeccionesProyectoEducativo::existe('nope'));
    }

    public function test_aplica_solo_a_media_superior(): void
    {
        $this->assertTrue(SeccionesProyectoEducativo::aplicaA('media_superior'));
        foreach (['inicial', 'preescolar', 'primaria', 'secundaria', 'superior', 'posgrado'] as $nivel) {
            $this->assertFalse(SeccionesProyectoEducativo::aplicaA($nivel), $nivel);
        }
    }

    public function test_nombre_de_slug_desconocido_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SeccionesProyectoEducativo::nombre('nope');
    }
}
