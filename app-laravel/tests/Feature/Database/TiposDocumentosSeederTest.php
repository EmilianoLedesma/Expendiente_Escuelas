<?php

namespace Tests\Feature\Database;

use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TiposDocumentosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_los_7_tipos_de_documento(): void
    {
        $this->seed(TiposDocumentosSeeder::class);

        $this->assertDatabaseCount('tipos_documentos', 13);

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'ine', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null]);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'acta_nacimiento', 'aplica_persona' => 'ambas', 'ambito' => 'escuela']);
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

        $this->assertDatabaseCount('tipos_documentos', 13);
    }

    public function test_agrega_los_documentos_faltantes_de_persona_moral_y_gestor(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'acta_constitutiva', 'aplica_persona' => 'moral']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'poder_gestor', 'aplica_persona' => 'fisica_con_gestor']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'acta_nacimiento', 'aplica_persona' => 'ambas']);
    }

    public function test_agrega_los_documentos_faltantes_del_plantel(): void
    {
        (new TiposDocumentosSeeder)->run();

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'visto_bueno_proteccion_civil', 'ambito' => 'plantel']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'plano_inmueble', 'ambito' => 'plantel']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'certificado_numero_oficial', 'ambito' => 'plantel']);
    }

    public function test_es_idempotente(): void
    {
        (new TiposDocumentosSeeder)->run();
        (new TiposDocumentosSeeder)->run();

        $this->assertSame(13, DB::table('tipos_documentos')->count());
    }
}
