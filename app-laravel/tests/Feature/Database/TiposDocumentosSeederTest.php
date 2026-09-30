<?php

namespace Tests\Feature\Database;

use App\Livewire\Tramite\Paso2Documentos;
use Database\Seeders\TiposDocumentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TiposDocumentosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_el_catalogo_completo(): void
    {
        $this->seed(TiposDocumentosSeeder::class);

        // 11 de Paso 2.2 (plantel/escuela) + 5 de Paso 2.4 (escuela_nivel).
        $this->assertDatabaseCount('tipos_documentos', 16);

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'ine', 'aplica_persona' => 'ambas', 'ambito' => 'escuela', 'vigencia_max_dias' => null]);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'acta_nacimiento', 'aplica_persona' => 'ambas', 'ambito' => 'escuela']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'escritura_poder_facultades', 'aplica_persona' => 'moral', 'ambito' => 'escuela']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'escritura_inmueble', 'aplica_persona' => 'ambas', 'ambito' => 'plantel']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'dictamen_uso_suelo', 'aplica_persona' => 'ambas', 'ambito' => 'plantel', 'vigencia_max_dias' => 30]);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'constancia_seguridad_estructural', 'aplica_persona' => 'ambas', 'ambito' => 'plantel']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'formato_solicitud', 'aplica_persona' => 'ambas', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => null]);
        $this->assertDatabaseMissing('tipos_documentos', ['clave' => 'recibo_pago_derechos_plantel']);
    }

    public function test_siembra_los_documentos_por_nivel_con_su_nivel(): void
    {
        (new TiposDocumentosSeeder)->run();
        $nivel = fn (string $clave) => DB::table('niveles_educativos')->where('clave', $clave)->value('id');

        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'recibo_pago_derechos', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => null, 'aplica_persona' => 'ambas']);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'acervo_bibliografico_primaria', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('primaria')]);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'acervo_bibliografico_secundaria', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('secundaria')]);
        $this->assertDatabaseHas('tipos_documentos', ['clave' => 'inventario_laboratorio', 'ambito' => 'escuela_nivel', 'nivel_educativo_id' => $nivel('secundaria')]);
        $this->assertNotNull($nivel('primaria'), 'el seeder debe sembrar niveles_educativos antes de referenciarlos');

        // Orden del checklist de 2.4 = orden por id.
        $this->assertSame(
            ['formato_solicitud', 'recibo_pago_derechos', 'acervo_bibliografico_primaria', 'acervo_bibliografico_secundaria', 'inventario_laboratorio'],
            DB::table('tipos_documentos')->where('ambito', 'escuela_nivel')->orderBy('id')->pluck('clave')->all(),
        );
    }

    /**
     * La vía genérica (guardarDocumentoSimple) no captura fecha de emisión, así
     * que una clave con vigencia_max_dias subida por ella nunca vencería. Toda
     * clave de Paso 2.2 con vigencia debe tener bloque propio.
     */
    public function test_solo_claves_con_datos_estructurados_de_paso_2_2_tienen_vigencia(): void
    {
        (new TiposDocumentosSeeder)->run();

        $conVigencia = DB::table('tipos_documentos')->whereIn('ambito', ['plantel', 'escuela'])->whereNotNull('vigencia_max_dias')->pluck('clave')->all();

        $this->assertSame(['dictamen_uso_suelo'], $conVigencia, 'control: el catálogo sí tiene una clave con vigencia');
        $this->assertSame([], array_values(array_diff($conVigencia, Paso2Documentos::CON_DATOS_ESTRUCTURADOS)));
    }

    public function test_correr_dos_veces_no_duplica(): void
    {
        $this->seed(TiposDocumentosSeeder::class);
        $this->seed(TiposDocumentosSeeder::class);

        $this->assertDatabaseCount('tipos_documentos', 16);
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

        $this->assertSame(16, DB::table('tipos_documentos')->count());
    }
}
