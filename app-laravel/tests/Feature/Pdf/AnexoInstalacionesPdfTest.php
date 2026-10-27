<?php

namespace Tests\Feature\Pdf;

use App\Application\Infraestructura\ConsultarAnexoInstalaciones;
use App\Infrastructure\Pdf\AnexoInstalacionesPdf;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\Solicitante;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Anexo 2 Instalaciones, T2: una sola plantilla genérica que replica el docx oficial. */
class AnexoInstalacionesPdfTest extends TestCase
{
    use RefreshDatabase;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new TiposEspaciosSeeder)->run();

        $this->plantel = Plantel::create(['calle' => 'Calle Uno', 'numero_ext' => '10', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => Solicitante::factory()->create()->id]);
    }

    private function nivel(string $clave): EscuelaNivel
    {
        return EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => NivelEducativo::where('clave', $clave)->value('id'),
            'estado_id' => DB::table('estados_expediente')->where('clave', 'en_captura')->value('id'),
            'tipo_tramite' => 'alta_nueva',
        ]);
    }

    private function espacio(string $clave, array $campos = []): int
    {
        return DB::table('instalaciones_espacios')->insertGetId($campos + [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id'),
        ]);
    }

    private function generar(EscuelaNivel $nivel): Response
    {
        return app(AnexoInstalacionesPdf::class)->generar($nivel, app(ConsultarAnexoInstalaciones::class)->ejecutar($nivel));
    }

    private function html(EscuelaNivel $nivel): string
    {
        return view('pdf.anexo2-instalaciones', [
            'anexo' => app(ConsultarAnexoInstalaciones::class)->ejecutar($nivel),
            'nivelNombre' => $nivel->nivelEducativo->nombre,
            'nivelClave' => $nivel->nivelEducativo->clave,
            'escuela' => $nivel->escuela,
        ])->render();
    }

    /** Texto plano sin etiquetas ni espacios repetidos, para asertar sobre lo que se imprime. */
    private function texto(EscuelaNivel $nivel): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $this->html($nivel)))));
    }

    public function test_genera_bytes_pdf_validos(): void
    {
        $respuesta = $this->generar($this->nivel('primaria'));

        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_imprime_los_encabezados_del_anexo(): void
    {
        $texto = $this->texto($this->nivel('primaria'));

        foreach (['ANEXO 2', 'INSTALACIONES', '1. DATOS GENERALES DEL INMUEBLE', '2. DESCRIPCION DE INSTALACIONES', 'ESPACIOS ADMINISTRATIVOS', 'AULAS PARA EL NIVEL EDUCATIVO', 'CUBÍCULOS', 'SANITARIOS', 'INSTALACIONES PARA ACTIVIDADES FÍSICAS Y RECREATIVAS', 'CENTRO DE DOCUMENTACIÓN O BIBLIOTECA', 'RELACION DE INSTITUCIONES DE SALUD', 'Fax:'] as $encabezado) {
            $this->assertStringContainsString($encabezado, $texto);
        }
    }

    public function test_sin_datos_no_imprime_no_ni_marca_nada(): void
    {
        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringNotContainsString('( X )', $texto);
        $this->assertStringContainsString('1.- ____', $texto);
        $this->assertStringContainsString('3.- ____', $texto);
    }

    public function test_inicial_sin_cubiculos_y_con_sus_filas_propias(): void
    {
        $this->espacio('filtro_recepcion', ['superficie_m2' => 4]);
        $id = DB::table('sanitarios')->insertGetId(['plantel_id' => $this->plantel->id, 'categoria' => 'alumnado_maternal']);
        DB::table('sanitarios_bacinicas')->insert(['sanitario_id' => $id, 'cantidad_bacinicas' => 6]);

        $texto = $this->texto($this->nivel('inicial'));

        $this->assertStringNotContainsString('CUBÍCULOS', $texto);
        $this->assertStringContainsString('Filtro / Recepción', $texto);
        $this->assertStringContainsString('Alumnado maternal', $texto);
        $this->assertStringContainsString('Bacinicas: 6', $texto);
    }

    public function test_generico_no_imprime_filtro_recepcion_si_no_esta_capturado(): void
    {
        $this->assertStringNotContainsString('Filtro', $this->texto($this->nivel('primaria')));
    }

    public function test_cubiculo_legado_en_inicial_va_en_otros(): void
    {
        $this->espacio('cubiculo', ['cantidad' => 1, 'destinado_a' => 'Algo']);

        $texto = $this->texto($this->nivel('inicial'));

        $this->assertStringContainsString('Cubículo de atención', $texto);
        $this->assertStringContainsString('Algo', $texto);
    }

    public function test_cubiculo_se_mapea_a_las_lineas_del_docx(): void
    {
        $this->espacio('cubiculo', ['cantidad' => 1, 'superficie_m2' => 9, 'destinado_a' => 'Atención a padres']);

        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringContainsString('Orientación educativa ( )', $texto);
        $this->assertStringContainsString('Atención a padres de familia ( X )', $texto);
    }

    public function test_cubiculo_con_otro_destino_va_en_linea_extra(): void
    {
        $this->espacio('cubiculo', ['cantidad' => 1, 'destinado_a' => 'Trabajo social']);

        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringContainsString('Trabajo social ( X )', $texto);
        $this->assertStringContainsString('Orientación educativa ( )', $texto);
    }

    public function test_recreativo_imprime_equipamiento_y_caracteristicas(): void
    {
        $this->espacio('arenero', ['cantidad' => 1, 'destinado_a' => 'Arena fina con barda']);

        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringContainsString('Equipamiento y características', $texto);
        $this->assertStringContainsString('Arena fina con barda', $texto);
    }

    public function test_biblioteca_imprime_revistas_sin_especializadas(): void
    {
        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringContainsString('Revistas', $texto);
        $this->assertStringNotContainsString('Revistas especializadas', $texto);
    }

    public function test_imprime_todos_los_servicios_cercanos_con_minimo_de_tres_lineas(): void
    {
        foreach (['Hospital A', 'Cruz Roja', 'Bomberos', 'Clínica D'] as $nombre) {
            DB::table('servicios_cercanos')->insert(['plantel_id' => $this->plantel->id, 'nombre' => $nombre, 'tipo' => 'salud', 'es_publico' => true]);
        }

        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringContainsString('1.- Hospital A', $texto);
        $this->assertStringContainsString('4.- Clínica D', $texto);
    }

    public function test_sanitario_marca_si_o_no_solo_con_dato(): void
    {
        DB::table('sanitarios')->insert(['plantel_id' => $this->plantel->id, 'categoria' => 'alumnado_masculino', 'cantidad_retretes' => 3, 'ventilacion_natural' => false, 'iluminacion_natural' => true]);

        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringContainsString('Alumnado masculino 3', $texto);
        $this->assertStringContainsString('SI ( ) NO ( X ) SI ( X ) NO ( )', $texto);
    }

    public function test_area_civica_se_oculta_en_inicial_aunque_este_capturada(): void
    {
        $this->plantel->update(['area_civica_m2' => 44, 'tiene_asta_bandera' => true]);

        $this->assertStringNotContainsString('ÁREA CÍVICA', $this->texto($this->nivel('inicial')));
    }

    public function test_area_civica_se_imprime_en_un_nivel_generico(): void
    {
        $this->plantel->update(['area_civica_m2' => 44]);

        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringContainsString('ÁREA CÍVICA', $texto);
        $this->assertStringContainsString('44', $texto);
    }

    public function test_otros_imprime_superficie_y_formato_del_campo_de_futbol_legado(): void
    {
        $campo = $this->espacio('campo_futbol', ['cantidad' => 1, 'superficie_m2' => 500]);
        DB::table('campos_futbol')->insert(['instalacion_espacio_id' => $campo, 'tipo_superficie' => 'pasto sintetico', 'formato' => 'futbol 7']);

        $texto = $this->texto($this->nivel('inicial'));

        $this->assertStringContainsString('pasto sintetico futbol 7', $texto);
    }

    public function test_imprime_predio_y_construido(): void
    {
        $this->plantel->update(['metros_totales' => 1200, 'metros_construidos' => 800]);

        $texto = $this->texto($this->nivel('primaria'));

        $this->assertStringContainsString('Predio: 1200', $texto);
        $this->assertStringContainsString('Construido: 800', $texto);
    }

    public function test_escapa_html_en_calle_y_destinado_a(): void
    {
        $this->plantel->update(['calle' => '<script>alert(1)</script>']);
        $this->espacio('arenero', ['cantidad' => 1, 'destinado_a' => '<script>x()</script>']);

        $html = $this->html($this->nivel('primaria'));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;x()&lt;/script&gt;', $html);
    }

    public function test_nunca_dice_obligatorio(): void
    {
        $this->assertStringNotContainsStringIgnoringCase('obligatorio', $this->html($this->nivel('primaria')));
    }

    public function test_el_pie_registra_pagina_n_de_m_en_el_pdf(): void
    {
        $bytes = $this->generar($this->nivel('primaria'))->getContent();

        // dompdf comprime los streams de contenido; el pie se escribe una vez por pagina (con bytes nulos entre letras).
        $contenido = '';
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $streams);
        foreach ($streams[1] as $flujo) {
            $contenido .= str_replace("\0", '', (string) @gzuncompress($flujo));
        }

        $this->assertMatchesRegularExpression('/P.{1,3}gina 1 de \d+/s', preg_replace('/\s/', ' ', $contenido));
    }
}
