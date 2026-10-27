<?php

namespace Tests\Feature\Livewire\Tramite;

use App\Application\EscuelaNiveles\MarcarPasoCompletado;
use App\Application\Excepciones\DatosInvalidos;
use App\Application\Excepciones\PrecondicionIncumplida;
use App\Application\Infraestructura\EditarInfraestructuraCompartida;
use App\Application\Infraestructura\RegistrarInfraestructuraNivel;
use App\Application\Tramite\TramiteEditable;
use App\Livewire\Tramite\Paso3\InfraestructuraNivel;
use App\Models\Escuela;
use App\Models\EscuelaNivel;
use App\Models\NivelEducativo;
use App\Models\Plantel;
use App\Models\ResponsableNivel;
use App\Models\Solicitante;
use App\Models\User;
use Database\Seeders\CatalogoMinimoSeeder;
use Database\Seeders\PasosCapturaSeeder;
use Database\Seeders\TiposEspaciosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\CompletaPaso2;
use Tests\Concerns\CompletaPaso24;
use Tests\TestCase;

class Paso3InfraestructuraNivelTest extends TestCase
{
    use CompletaPaso2;
    use CompletaPaso24;
    use RefreshDatabase;

    private Solicitante $solicitante;

    private Plantel $plantel;

    private Escuela $escuela;

    protected function setUp(): void
    {
        parent::setUp();

        (new CatalogoMinimoSeeder)->run();
        (new PasosCapturaSeeder)->run();
        (new TiposEspaciosSeeder)->run();

        $this->solicitante = Solicitante::factory()->create();
        $this->plantel = Plantel::create(['calle' => 'Calle 1', 'colonia' => 'Centro', 'municipio' => 'Querétaro', 'codigo_postal' => '76000']);
        $this->escuela = Escuela::create(['plantel_id' => $this->plantel->id, 'solicitante_id' => $this->solicitante->id]);
        $this->completarPaso2($this->escuela->id);
    }

    private function escuelaNivel(string $claveNivel): EscuelaNivel
    {
        $nivel = NivelEducativo::where('clave', $claveNivel)->first();
        $estadoId = DB::table('estados_expediente')->where('clave', 'en_captura')->value('id');

        $escuelaNivel = EscuelaNivel::create([
            'escuela_id' => $this->escuela->id,
            'nivel_educativo_id' => $nivel->id,
            'estado_id' => $estadoId,
            'tipo_tramite' => 'alta_nueva',
        ]);
        $this->completarPaso24($escuelaNivel->id);
        // WS-1.3: Infraestructura solo es alcanzable con Inmueble completado.
        (new MarcarPasoCompletado)->ejecutar($escuelaNivel->id, 'inmueble');

        return $escuelaNivel;
    }

    private function idTipo(string $clave): int
    {
        return (int) DB::table('tipos_espacios')->where('clave', $clave)->value('id');
    }

    public function test_muestra_solo_los_espacios_aplicables_al_nivel(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]));

        $response->assertOk();
        $response->assertSee('Centro de documentación / Biblioteca', false);
        $response->assertDontSee('Filtro / Recepción', false);
    }

    public function test_reparte_la_captura_en_pasos_y_solo_el_ultimo_guarda(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        $html = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]))
            ->getContent();

        $this->assertStringContainsString('data-stepper-nav', $html);
        $this->assertStringContainsString('data-paso="0"', $html);
        preg_match_all('/data-paso="(\d+)"/', $html, $m);
        $ultimo = max(array_map('intval', $m[1]));
        $this->assertGreaterThanOrEqual(3, $ultimo);
        $this->assertSame(range(0, $ultimo), array_map('intval', $m[1]), 'Cada panel lleva su propio índice, sin saltos.');

        $inicioUltimo = strpos($html, 'data-paso="'.$ultimo.'"');
        $this->assertSame(1, substr_count($html, 'Guardar y continuar'));
        $this->assertGreaterThan($inicioUltimo, strpos($html, 'Guardar y continuar'));
        // Aulas ya no es el último panel (sigue el orden del Anexo 2): Guardar va en el último, Aulas antes.
        $this->assertLessThan($inicioUltimo, strpos($html, 'Número de aulas'));
    }

    /** @return list<string> títulos de los paneles del stepper, en orden de aparición. */
    private function titulosDePaneles(string $html): array
    {
        preg_match_all('/<div role="group" aria-label="([^"]+)" data-paso="(\d+)"/', $html, $m);
        $this->assertSame(range(0, count($m[2]) - 1), array_map('intval', $m[2]), 'Índices consecutivos.');

        return array_map('html_entity_decode', $m[1]);
    }

    public function test_paneles_en_el_orden_del_anexo_para_un_nivel_generico(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        $html = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]))
            ->getContent();

        $this->assertSame(
            ['Espacios administrativos', 'Aulas del nivel', 'Cubículos', 'Sanitarios', 'Actividades físicas y recreativas', 'Instalaciones especiales'],
            $this->titulosDePaneles($html),
        );
    }

    /** Un panel con más de 3 espacios se pagina de 3 en 3 (Alpine); uno corto no muestra navegación. */
    public function test_los_paneles_largos_se_paginan_de_tres_en_tres(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        $html = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]))
            ->getContent();

        // Primaria: administrativo 7 → 3 páginas; recreativo 8 → 3; especial 9 → 3; cubículo 1 → sin navegación.
        $this->assertSame(3, substr_count($html, 'Página 1 de 3'));
        $this->assertSame(3, substr_count($html, 'aria-label="Paginación de espacios"'));
        $this->assertStringContainsString('x-show="pagina === 2"', $html);
    }

    public function test_paneles_en_el_orden_del_anexo_para_inicial_sin_cubiculos(): void
    {
        $inicial = $this->escuelaNivel('inicial');

        $html = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $inicial->id]))
            ->getContent();

        $this->assertSame(
            ['Espacios administrativos', 'Aulas del nivel', 'Sanitarios', 'Áreas recreativas', 'Instalaciones especiales'],
            $this->titulosDePaneles($html),
        );
    }

    public function test_destinado_a_de_recreativo_dice_equipamiento_y_los_demas_conservan_destinado_a(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $canchaId = $this->idTipo('cancha_usos_multiples');
        $direccionId = $this->idTipo('direccion');

        $html = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]))
            ->getContent();

        $this->assertMatchesRegularExpression('/<label for="espacios\.'.$canchaId.'\.destinadoA"[^>]*>\s*Equipamiento y características/', $html);
        $this->assertMatchesRegularExpression('/<label for="espacios\.'.$direccionId.'\.destinadoA"[^>]*>\s*Destinado a/', $html);
    }

    public function test_lo_ya_capturado_editable_usa_la_misma_etiqueta_de_equipamiento(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $canchaId = $this->idTipo('cancha_usos_multiples');
        $direccionId = $this->idTipo('direccion');
        DB::table('instalaciones_espacios')->insert([
            ['plantel_id' => $this->plantel->id, 'tipo_espacio_id' => $canchaId, 'cantidad' => 1],
            ['plantel_id' => $this->plantel->id, 'tipo_espacio_id' => $direccionId, 'cantidad' => 1],
        ]);

        $html = Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->html();

        $this->assertMatchesRegularExpression('/<label for="edicionEspacios\.'.$canchaId.'\.destinadoA"[^>]*>\s*Equipamiento y características/', $html);
        $this->assertMatchesRegularExpression('/<label for="edicionEspacios\.'.$direccionId.'\.destinadoA"[^>]*>\s*Destinado a/', $html);
    }

    public function test_dueno_y_responsable_ven_el_enlace_para_descargar_el_anexo(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $href = route('tramite.paso3.infraestructura.anexo-instalaciones', ['escuelaNivel' => $primaria->id]);
        $delegado = User::factory()->create();
        ResponsableNivel::create(['user_id' => $delegado->id, 'escuela_nivel_id' => $primaria->id, 'invitado_por_solicitante_id' => $this->solicitante->id]);

        foreach ([$this->solicitante->user, $delegado] as $usuario) {
            Livewire::actingAs($usuario)
                ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
                ->assertSeeHtml('href="'.$href.'"')
                ->assertSee('Descargar Anexo');
        }
    }

    public function test_sin_la_habilidad_descargar_anexo_no_se_ofrece_el_enlace(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        Gate::before(fn ($user, $ability) => $ability === 'descargarAnexo' ? false : null);

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->assertDontSee('Descargar Anexo');
    }

    public function test_inicial_ve_filtro_recepcion_y_no_biblioteca(): void
    {
        $inicial = $this->escuelaNivel('inicial');

        $response = $this->actingAs($this->solicitante->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $inicial->id]));

        $response->assertOk();
        $response->assertSee('Filtro / Recepción', false);
        $response->assertDontSee('Centro de documentación / Biblioteca', false);
    }

    public function test_inicial_ve_las_categorias_de_sanitarios_de_inicial(): void
    {
        $inicial = $this->escuelaNivel('inicial');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $inicial])
            ->assertSee('Bacinicas');
    }

    public function test_guardar_escribe_espacios_aulas_y_redirige_a_mobiliario(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set("espacios.{$direccionId}.superficieM2", 18.5)
            ->set('sanitarios.alumnado_masculino.cantidadRetretes', 4)
            ->set('sanitarios.alumnado_masculino.superficieM2', 12)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertSessionHas('toast.titulo', 'Infraestructura guardada')
            ->assertRedirect(route('tramite.paso3-mobiliario', ['escuelaNivel' => $primaria->id]));

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $direccionId,
            'cantidad' => 1,
        ]);
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $primaria->id, 'numero_aulas' => 6]);
    }

    public function test_no_escribe_los_espacios_dejados_en_blanco(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        $this->assertSame(1, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count());
    }

    public function test_la_etiqueta_de_superficie_de_aulas_ya_no_dice_opcional(): void
    {
        $html = Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $this->escuelaNivel('primaria')])
            ->html();

        preg_match('/<label for="superficieAulasM2".*?<\/label>/s', $html, $etiqueta);
        $this->assertStringContainsString('Superficie total (m²)', $etiqueta[0] ?? '');
        $this->assertStringNotContainsString('(opcional)', $etiqueta[0]);
    }

    public function test_el_conjunto_de_biblioteca_es_el_ancla_del_error_de_acervo(): void
    {
        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $this->escuelaNivel('primaria')])
            ->assertSeeHtml('<fieldset id="materialesBiblioteca"');
    }

    public function test_la_superficie_de_aulas_es_obligatoria(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        $prueba = Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set('numeroAulas', 4)
            ->call('guardar')
            ->assertHasErrors(['superficieAulasM2' => 'required']);

        $this->assertSame('El campo superficie total de aulas es obligatorio.', $prueba->errors()->first('superficieAulasM2'));
        $this->assertDatabaseCount('aulas_nivel', 0);
    }

    // WS-7a §3.1: the use case's per-row requirement reaches the field through addError().
    public function test_un_espacio_recreativo_sin_superficie_marca_su_campo(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $verdesId = $this->idTipo('areas_verdes');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$verdesId}.cantidad", 1)
            ->set('numeroAulas', 4)
            ->set('superficieAulasM2', 120)
            ->call('guardar')
            ->assertHasErrors(["espacios.{$verdesId}.superficieM2"])
            ->assertNoRedirect();

        $this->assertDatabaseCount('instalaciones_espacios', 0);
    }

    public function test_numero_de_aulas_es_obligatorio(): void
    {
        $primaria = $this->escuelaNivel('primaria');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set('numeroAulas', '')
            ->call('guardar')
            ->assertHasErrors('numeroAulas');

        $this->assertDatabaseCount('aulas_nivel', 0);
    }

    public function test_un_segundo_nivel_no_vuelve_a_ofrecer_lo_que_el_plantel_ya_capturo(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set('sanitarios.alumnado_masculino.cantidadRetretes', 4)
            ->set('sanitarios.alumnado_masculino.superficieM2', 12)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        $preescolar = $this->escuelaNivel('preescolar');

        $testable = Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $preescolar]);

        $this->assertNotContains($direccionId, $testable->instance()->tiposAplicables()->pluck('id')->all());
        $this->assertNotContains('alumnado_masculino', $testable->instance()->categoriasSanitarios());

        $testable->set('numeroAulas', 3)
            ->set('superficieAulasM2', 120)
            ->call('guardar')
            ->assertRedirect(route('tramite.paso3-mobiliario', ['escuelaNivel' => $preescolar->id]));

        $this->assertSame(1, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->where('tipo_espacio_id', $direccionId)->count());
        $this->assertDatabaseHas('aulas_nivel', ['escuela_nivel_id' => $preescolar->id, 'numero_aulas' => 3]);
    }

    public function test_un_segundo_nivel_captura_sus_propios_campos_exclusivos_de_inicial(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set('sanitarios.alumnado_masculino.cantidadRetretes', 4)
            ->set('sanitarios.alumnado_masculino.superficieM2', 12)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        $inicial = $this->escuelaNivel('inicial');
        $filtroId = $this->idTipo('filtro_recepcion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $inicial])
            ->assertSee('Filtro / Recepción')
            ->set("espacios.{$filtroId}.cantidad", 1)
            ->set('sanitarios.alumnado_maternal.cantidadRetretes', 2)
            ->set('sanitarios.alumnado_maternal.superficieM2', 8)
            ->set('sanitarios.alumnado_maternal.cantidadBacinicas', 3)
            ->set('numeroAulas', 2)
            ->set('superficieAulasM2', 60)
            ->call('guardar');

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $filtroId,
        ]);
        $this->assertSame(2, DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->count());
        $this->assertDatabaseHas('sanitarios', [
            'plantel_id' => $this->plantel->id,
            'categoria' => 'alumnado_maternal',
            'cantidad_retretes' => 2,
        ]);
        $this->assertSame(2, DB::table('sanitarios')->where('plantel_id', $this->plantel->id)->count());
    }

    public function test_bodega_destinado_a_limpieza_se_guarda(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bodegaId = $this->idTipo('bodega');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$bodegaId}.cantidad", 1)
            ->set("espacios.{$bodegaId}.destinadoA", 'limpieza')
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $bodegaId,
            'destinado_a' => 'limpieza',
        ]);
    }

    public function test_bodega_destinado_a_invalido_no_escribe_nada(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bodegaId = $this->idTipo('bodega');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$bodegaId}.cantidad", 1)
            ->set("espacios.{$bodegaId}.destinadoA", 'otra-cosa-no-valida')
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertHasErrors("espacios.{$bodegaId}.destinadoA");

        $this->assertDatabaseCount('instalaciones_espacios', 0);
    }

    public function test_biblioteca_con_solo_un_material_de_id_inexistente_se_rechaza_por_falta_de_acervo(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bibliotecaId = $this->idTipo('biblioteca');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$bibliotecaId}.cantidad", 1)
            ->set('materialesBiblioteca.9999.numeroTitulos', 1)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        // El id inexistente se ignora: la biblioteca queda sin acervo y se rechaza (no hay 500).
        $this->assertDatabaseCount('biblioteca_materiales', 0);
        $this->assertDatabaseCount('instalaciones_espacios', 0);
    }

    public function test_un_material_de_id_inexistente_se_ignora_si_hay_un_material_valido(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bibliotecaId = $this->idTipo('biblioteca');
        $librosId = (int) DB::table('tipos_material_biblioteca')->where('clave', 'libros')->value('id');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$bibliotecaId}.cantidad", 1)
            ->set("materialesBiblioteca.{$librosId}.numeroTitulos", 300)
            ->set('materialesBiblioteca.9999.numeroTitulos', 1)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        $this->assertDatabaseCount('biblioteca_materiales', 1);
        $this->assertDatabaseHas('biblioteca_materiales', ['tipo_material_id' => $librosId, 'numero_titulos' => 300]);
    }

    // WS-2.1 — materials entered without a biblioteca cantidad must not vanish:
    // a biblioteca_materiales row requires an instalaciones_espacios row (FK NOT NULL),
    // so materials present implies the biblioteca espacio row is created.
    public function test_material_biblioteca_sin_cantidad_se_guarda_con_fila_de_biblioteca(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $bibliotecaId = $this->idTipo('biblioteca');
        $librosId = (int) DB::table('tipos_material_biblioteca')->where('clave', 'libros')->value('id');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("materialesBiblioteca.$librosId.numeroTitulos", 300)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        $bibliotecaEspacioId = DB::table('instalaciones_espacios')
            ->where('plantel_id', $this->plantel->id)
            ->where('tipo_espacio_id', $bibliotecaId)
            ->value('id');
        $this->assertNotNull($bibliotecaEspacioId, 'Los materiales de biblioteca se descartaron en silencio.');
        $this->assertDatabaseHas('biblioteca_materiales', [
            'instalacion_espacio_id' => $bibliotecaEspacioId,
            'numero_titulos' => 300,
        ]);
    }

    // WS-2.1 fix round 1 — an unchecked/blurred checkbox commits boolean false,
    // which must NOT count as "content"; otherwise a tipo the user never
    // touched gets a junk row and (per ADR-005) is permanently marked
    // capturado for the plantel.
    public function test_checkboxes_sin_marcar_no_crean_un_espacio_fantasma(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.ventilacionNatural", false)
            ->set("espacios.{$direccionId}.iluminacionNatural", false)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        $this->assertDatabaseMissing('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $direccionId,
        ]);
    }

    // WS-2 item 1 — un espacio con solo el formato de campo_futbol capturado
    // no debe perderse: el componente lo construía pero el caso de uso no lo
    // contaba como "dato significativo".
    public function test_campo_futbol_con_solo_formato_se_guarda(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $campoFutbolId = $this->idTipo('campo_futbol');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$campoFutbolId}.campoFutbolFormato", '7')
            ->set("espacios.{$campoFutbolId}.superficieM2", 800)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar');

        $this->assertDatabaseHas('instalaciones_espacios', [
            'plantel_id' => $this->plantel->id,
            'tipo_espacio_id' => $campoFutbolId,
        ]);
    }

    public function test_un_no_dueno_recibe_403(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $otro = Solicitante::factory()->create();

        $response = $this->actingAs($otro->user)
            ->get(route('tramite.paso3-infraestructura', ['escuelaNivel' => $primaria->id]));

        $response->assertForbidden();
    }

    // WS-2.4a — un DatosInvalidos lanzado por el caso de uso debe convertirse
    // en errores de campo, no en un 500.
    public function test_datos_invalidos_del_caso_de_uso_se_convierte_en_errores_de_campo(): void
    {
        $primaria = $this->escuelaNivel('primaria');
        $direccionId = $this->idTipo('direccion');

        $this->mock(RegistrarInfraestructuraNivel::class, function ($mock) {
            $mock->shouldReceive('ejecutar')->andThrow(new DatosInvalidos([
                'numeroAulas' => 'El número de aulas no puede ser negativo.',
            ]));
        });

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$direccionId}.cantidad", 1)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertHasErrors('numeroAulas');

        $this->assertDatabaseCount('aulas_nivel', 0);
    }

    /** Primaria captura dirección, biblioteca con acervo y un sanitario; devuelve el escuela_nivel. */
    private function capturarPrimaria(): EscuelaNivel
    {
        $primaria = $this->escuelaNivel('primaria');
        $librosId = (int) DB::table('tipos_material_biblioteca')->where('clave', 'libros')->value('id');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("espacios.{$this->idTipo('direccion')}.cantidad", 1)
            ->set("espacios.{$this->idTipo('direccion')}.superficieM2", 18.5)
            ->set("espacios.{$this->idTipo('direccion')}.ventilacionNatural", false)
            ->set("espacios.{$this->idTipo('campo_futbol')}.superficieM2", 800)
            ->set("espacios.{$this->idTipo('campo_futbol')}.campoFutbolFormato", '7')
            ->set("espacios.{$this->idTipo('biblioteca')}.cantidad", 1)
            ->set("materialesBiblioteca.{$librosId}.numeroTitulos", 300)
            ->set('sanitarios.alumnado_masculino.cantidadRetretes', 4)
            ->set('sanitarios.alumnado_masculino.superficieM2', 12)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertHasNoErrors();

        return $primaria;
    }

    public function test_el_dueno_ve_lo_capturado_precargado_y_lo_edita(): void
    {
        $primaria = $this->capturarPrimaria();
        $direccionId = $this->idTipo('direccion');
        // Bacinicas solo existen en alumnado_maternal (lo captura Inicial): se siembra directo.
        $maternalId = DB::table('sanitarios')->insertGetId(['plantel_id' => $this->plantel->id, 'categoria' => 'alumnado_maternal', 'cantidad_retretes' => 2, 'superficie_m2' => 8]);
        DB::table('sanitarios_bacinicas')->insert(['sanitario_id' => $maternalId, 'cantidad_bacinicas' => 3]);

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->assertSet("edicionEspacios.{$direccionId}.cantidad", 1)
            ->assertSet('edicionSanitarios.alumnado_masculino.cantidadRetretes', 4)
            ->assertSeeHtml('id="edicionEspacios.'.$direccionId.'.cantidad"')
            ->set("edicionEspacios.{$direccionId}.cantidad", 2)
            ->call('guardarEdicion')
            ->assertHasNoErrors();

        $direccion = DB::table('instalaciones_espacios')->where('plantel_id', $this->plantel->id)->where('tipo_espacio_id', $direccionId)->first();
        $this->assertSame(2, (int) $direccion->cantidad);
        $this->assertEquals(18.5, (float) $direccion->superficie_m2);
        // La edición reemplaza la fila entera: lo que no se tocó se reenvía y no se pierde.
        $this->assertDatabaseHas('biblioteca_materiales', ['numero_titulos' => 300]);
        $this->assertDatabaseHas('sanitarios', ['plantel_id' => $this->plantel->id, 'categoria' => 'alumnado_masculino', 'cantidad_retretes' => 4]);
        $this->assertFalse($direccion->ventilacion_natural, 'false es un valor: no debe volverse null.');
        $this->assertNull($direccion->iluminacion_natural);
        $this->assertDatabaseHas('sanitarios_bacinicas', ['sanitario_id' => $maternalId, 'cantidad_bacinicas' => 3]);
        $campoId = DB::table('instalaciones_espacios')->where('tipo_espacio_id', $this->idTipo('campo_futbol'))->value('id');
        $this->assertDatabaseHas('campos_futbol', ['instalacion_espacio_id' => $campoId, 'formato' => '7']);
    }

    public function test_guardar_no_descarta_cambios_sin_guardar_en_lo_ya_registrado(): void
    {
        $primaria = $this->capturarPrimaria();
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("edicionEspacios.{$direccionId}.cantidad", 5)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertHasErrors('edicionPendiente')
            ->assertNoRedirect();

        $this->assertSame(1, (int) DB::table('instalaciones_espacios')->where('tipo_espacio_id', $direccionId)->value('cantidad'));
    }

    public function test_marcar_quitar_sin_guardar_tambien_bloquea_guardar(): void
    {
        $primaria = $this->capturarPrimaria();

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set('edicionSanitarios.alumnado_masculino.quitar', true)
            ->set('numeroAulas', 6)
            ->set('superficieAulasM2', 240)
            ->call('guardar')
            ->assertHasErrors('edicionPendiente')
            ->assertNoRedirect();
    }

    public function test_lo_ya_registrado_sin_tocar_no_bloquea_guardar(): void
    {
        $primaria = $this->capturarPrimaria();

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set('numeroAulas', 7)
            ->set('superficieAulasM2', 250)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('tramite.paso3-mobiliario', ['escuelaNivel' => $primaria->id]));
    }

    public function test_el_responsable_por_nivel_no_puede_editar_lo_capturado(): void
    {
        $primaria = $this->capturarPrimaria();
        $direccionId = $this->idTipo('direccion');
        $delegado = User::factory()->create();
        ResponsableNivel::create(['user_id' => $delegado->id, 'escuela_nivel_id' => $primaria->id, 'invitado_por_solicitante_id' => $this->solicitante->id]);

        Livewire::actingAs($delegado)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->assertSee('Infraestructura del plantel (ya registrada)')
            ->assertDontSeeHtml('id="edicionEspacios.'.$direccionId.'.cantidad"')
            ->set("edicionEspacios.{$direccionId}", ['cantidad' => 9])
            ->call('guardarEdicion')
            ->assertForbidden();

        $this->assertSame(1, (int) DB::table('instalaciones_espacios')->where('tipo_espacio_id', $direccionId)->value('cantidad'));
    }

    public function test_bloqueado_muestra_el_aviso_y_oculta_quitar(): void
    {
        $primaria = $this->capturarPrimaria();
        $preescolar = $this->escuelaNivel('preescolar');
        DB::table('aulas_nivel')->insert(['escuela_nivel_id' => $preescolar->id, 'numero_aulas' => 2, 'superficie_m2' => 60]);
        $direccionId = $this->idTipo('direccion');

        $html = Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->assertSee('Otro nivel ya capturó: solo puedes completar campos vacíos')
            ->assertDontSee('Quitar este registro')
            ->html();

        // Lo ya capturado queda de solo lectura; lo vacío se puede completar.
        $this->assertMatchesRegularExpression('/<input[^>]*id="edicionEspacios\.'.$direccionId.'\.cantidad"[^>]*disabled/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="edicionEspacios\.'.$direccionId.'\.capacidadPromedio"[^>]*disabled/s', $html);
    }

    public function test_error_de_validacion_se_muestra_en_el_campo(): void
    {
        $primaria = $this->capturarPrimaria();
        $direccionId = $this->idTipo('direccion');

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->set("edicionEspacios.{$direccionId}.cantidad", null)
            ->set("edicionEspacios.{$direccionId}.superficieM2", null)
            ->call('guardarEdicion')
            ->assertHasErrors(["edicionEspacios.{$direccionId}.tipoEspacioId"])
            ->assertSeeHtml('id="edicionEspacios.'.$direccionId.'.tipoEspacioId"');

        $this->assertSame(1, (int) DB::table('instalaciones_espacios')->where('tipo_espacio_id', $direccionId)->value('cantidad'));
    }

    public function test_tramite_hermano_enviado_muestra_el_error_de_plantel(): void
    {
        $primaria = $this->capturarPrimaria();

        $this->mock(EditarInfraestructuraCompartida::class, function ($mock) {
            $mock->shouldReceive('ejecutar')
                ->andThrow(new PrecondicionIncumplida(TramiteEditable::ENVIADO, 'Un trámite de este plantel ya se envió a SEDEQ.'));
        });

        Livewire::actingAs($this->solicitante->user)
            ->test(InfraestructuraNivel::class, ['escuelaNivel' => $primaria])
            ->call('guardarEdicion')
            ->assertNoRedirect()
            ->assertHasErrors('plantel')
            ->assertDispatched('toast', tipo: 'error', titulo: 'No se guardó', descripcion: 'Un trámite de este plantel ya se envió a SEDEQ.');
    }
}
