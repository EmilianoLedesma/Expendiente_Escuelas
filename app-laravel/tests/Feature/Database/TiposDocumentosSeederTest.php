<?php

namespace Tests\Feature\Database;

use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TiposDocumentosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_los_7_tipos_de_documento(): void
    {
        $this->seed(TiposDocumentosSeeder::class);

        $this->assertDatabaseCount('tipos_documentos', 7);

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'ine', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null]);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'acta_nacimiento', 'aplica_persona' => 'fisica', 'ambito' => 'escuela']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'escritura_poder_facultades', 'aplica_persona' => 'moral', 'ambito' => 'escuela']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'escritura_inmueble', 'aplica_persona' => 'ambas', 'ambito' => 'plantel']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'dictamen_uso_suelo', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => 30]);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'constancia_seguridad_estructural', 'aplica_persona' => 'ambas', 'ambito' => 'plantel']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'formato_solicitud', 'aplica_persona' => 'ambas', 'ambito' => 'escuela']);
    }

    public function test_correr_dos_veces_no_duplica(): void
    {
        $this->seed(TiposDocumentosSeeder::class);
        $this->seed(TiposDocumentosSeeder::class);

        $this->assertDatabaseCount('tipos_documentos', 7);
    }
}
