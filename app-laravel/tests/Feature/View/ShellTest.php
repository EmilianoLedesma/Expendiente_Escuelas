<?php

namespace Tests\Feature\View;

use App\Models\Solicitante;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_paginas_de_acceso_usan_el_shell_institucional(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Saltar al contenido')
            ->assertSee('href="#contenido"', false)
            ->assertSee('id="contenido"', false)
            ->assertSee('Secretaría de Educación del Estado de Querétaro')
            ->assertSee('Trámite de Incorporación de Escuelas Particulares')
            ->assertSee('Sistema de Incorporación · versión MVP');
    }

    public function test_las_paginas_del_tramite_usan_el_shell_con_la_cuenta(): void
    {
        $solicitante = Solicitante::factory()->create();

        $this->actingAs($solicitante->user)
            ->get(route('tramite.preregistro'))
            ->assertOk()
            ->assertSee('Saltar al contenido')
            ->assertSee('id="contenido"', false)
            ->assertSee('Trámite de Incorporación de Escuelas Particulares')
            ->assertSee($solicitante->user->name)
            ->assertSee('Cerrar sesión')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Sistema de Incorporación · versión MVP');
    }

    /** Las pruebas de round-trip toman el primer wire:snapshot: el shell no debe montar Livewire antes del contenido. */
    public function test_el_shell_no_monta_componentes_livewire_antes_del_contenido(): void
    {
        $html = $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.preregistro'))
            ->getContent();

        $this->assertLessThan(strpos($html, 'wire:snapshot'), strpos($html, 'id="contenido"'));
    }

    public function test_el_css_global_define_foco_visible_y_movimiento_reducido(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }

    /** Queja del dueño (2026-09-25): los puntos del asistente aparecían en Mis trámites. */
    public function test_mis_tramites_no_muestra_recorrido_ni_puntos_del_asistente(): void
    {
        $this->actingAs(Solicitante::factory()->create()->user)
            ->get(route('tramite.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('data-estado=', false)
            ->assertDontSee('Secciones del trámite');
    }

    // --- Barra institucional, encabezado y pie (estilo tableros_municipales, 2026-10-06) ---

    /** @return array<string, array{string, bool}> */
    public static function paginas(): array
    {
        return [
            'login' => ['login', false],
            'register' => ['register', false],
            'mis trámites' => ['tramite.index', true],
            'paso del asistente' => ['tramite.preregistro', true],
            'perfil' => ['profile', true],
        ];
    }

    private function html(string $ruta, bool $autenticado): string
    {
        if ($autenticado) {
            $this->actingAs(Solicitante::factory()->create()->user);
        }

        return $this->get(route($ruta))->assertOk()->getContent();
    }

    private function dom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR);

        return new DOMXPath($dom);
    }

    #[DataProvider('paginas')]
    public function test_cada_layout_tiene_barra_encabezado_navegacion_y_pie_unicos(string $ruta, bool $autenticado): void
    {
        $x = $this->dom($this->html($ruta, $autenticado));

        // Un <header>/<footer> dentro de <main> no es landmark (banner/contentinfo); solo cuentan los de fuera.
        $this->assertSame(1, $x->query('//header[not(ancestor::main)]')->length, 'un solo banner');
        $this->assertSame(1, $x->query('//footer[not(ancestor::main)]')->length, 'un solo contentinfo');
        $this->assertSame(1, $x->query('//main[@id="contenido"]')->length);
        $this->assertSame(1, $x->query('//header//nav[@aria-label="Enlaces institucionales"]')->length);
        $this->assertSame($autenticado ? 1 : 0, $x->query('//header//nav[@aria-label="Cuenta"]')->length);
        $this->assertSame(1, $x->query('//nav[@aria-label="Enlaces institucionales"]/ul[contains(concat(" ", @class, " "), " flex-wrap ")]')->length, 'reflujo a 320px (1.4.10)');
        // El nombre se refresca al guardar el perfil (como el menú Breeze eliminado), sin Livewire en el shell.
        $this->assertSame($autenticado ? 1 : 0, $x->query('//header//nav[@aria-label="Cuenta"]//*[@*[name()="x-on:profile-updated.window"]]')->length);

        $primerEnlace = $x->query('//body//a')->item(0);
        $this->assertInstanceOf(DOMElement::class, $primerEnlace);
        $this->assertSame('#contenido', $primerEnlace->getAttribute('href'), 'el salto al contenido es el primer enlace');

        $logo = $x->query('//header//a/img[@alt="SEDEQ - Secretaría de Educación del Estado de Querétaro"]');
        $this->assertSame(1, $logo->length);
        $this->assertStringEndsWith('/img/layout_set_logo.png', $logo->item(0)->getAttribute('src'));

        $escudo = $x->query('//footer//img[@alt="Gobierno de Querétaro"]');
        $this->assertSame(1, $escudo->length);
        $this->assertStringEndsWith('/img/heraldicas.png', $escudo->item(0)->getAttribute('src'));

        $this->assertSame(1, $x->query('//footer//h2')->length, 'el pie abre con un h2 y sus columnas son h3');
        $this->assertSame(4, $x->query('//footer//h3')->length);
    }

    #[DataProvider('paginas')]
    public function test_no_hay_recursos_enlazados_desde_servidores_externos(string $ruta, bool $autenticado): void
    {
        $html = $this->html($ruta, $autenticado);
        $x = $this->dom($html);
        $propio = url('/');

        // Todo recurso cargado (img/script/srcset/<link href>) es relativo o de la propia app: nada de CDN ni protocolo relativo.
        $urls = [];
        foreach ($x->query('//*[@src]/@src | //link[@href]/@href') as $atributo) {
            $urls[] = trim($atributo->nodeValue);
        }
        foreach ($x->query('//*[@srcset]/@srcset') as $atributo) {
            foreach (explode(',', $atributo->nodeValue) as $candidato) {
                $urls[] = strtok(trim($candidato), ' ');
            }
        }

        $this->assertNotEmpty($urls);
        foreach ($urls as $u) {
            $externo = (bool) preg_match('#^(https?:)?//#i', $u) && ! str_starts_with($u, $propio.'/');
            $this->assertFalse($externo, 'recurso externo: '.$u);
        }

        $this->assertDoesNotMatchRegularExpression('#url\(\s*[\'"]?(https?:)?//#i', $html);
    }

    public function test_hover_de_la_barra_no_aclara_el_azul(): void
    {
        // hover:bg-white/10 sobre #266fb6 deja el texto blanco en 4.32:1 (< 4.5); se oscurece en su lugar.
        foreach (['encabezado', 'redes', 'pie'] as $vista) {
            $this->assertStringNotContainsString('hover:bg-white/', file_get_contents(resource_path("views/components/shell/{$vista}.blade.php")), $vista);
        }
    }

    public function test_el_foco_en_las_franjas_oscuras_es_blanco_y_hacia_dentro(): void
    {
        $css = preg_replace('/\s+/', ' ', file_get_contents(resource_path('css/app.css')));

        // Barra de una fila sin padding vertical: un outline-offset positivo recorta el anillo (arriba) o lo pinta blanco sobre blanco (abajo).
        $this->assertMatchesRegularExpression('/\.shell-oscuro :focus-visible \{[^}]*outline-color: theme\(\'colors\.white\'\) !important;[^}]*outline-offset: -3px !important;/', $css);
    }

    public function test_el_telefono_del_directorio_se_define_una_sola_vez(): void
    {
        $fuente = file_get_contents(config_path('sedeq.php'));
        $this->assertSame(1, substr_count($fuente, '4422385000'));
        $this->assertSame(0, substr_count($fuente, '238 5000'), 'el formato se deriva, no se escribe');

        $digitos = config('sedeq.directorio');
        $this->assertContains('tel:'.$digitos, array_column(config('sedeq.redes'), 'url'));

        $this->get('/login')
            ->assertSee('442 238 5000')
            ->assertSee('Directorio (442) 238 5000');
    }

    public function test_el_css_propio_no_trae_recursos_externos(): void
    {
        foreach (['css/app.css', 'css/fonts.css'] as $archivo) {
            $this->assertDoesNotMatchRegularExpression('#https?://#', file_get_contents(resource_path($archivo)), $archivo);
        }
    }

    #[DataProvider('paginas')]
    public function test_los_enlaces_externos_abren_seguro_y_todo_enlace_tiene_nombre(string $ruta, bool $autenticado): void
    {
        $x = $this->dom($this->html($ruta, $autenticado));

        foreach ($x->query('//header//a | //footer//a') as $a) {
            /** @var DOMElement $a */
            $alts = implode(' ', array_map(fn ($img) => $img->getAttribute('alt'), iterator_to_array($a->getElementsByTagName('img'))));
            $nombre = trim($a->textContent.' '.$a->getAttribute('aria-label').' '.$alts);
            $this->assertNotSame('', $nombre, 'enlace sin nombre accesible: '.$a->getAttribute('href'));

            $href = $a->getAttribute('href');
            if (str_starts_with($href, 'http') && ! str_starts_with($href, url('/'))) {
                $this->assertSame('_blank', $a->getAttribute('target'), $a->getAttribute('href'));
                $this->assertSame('noopener noreferrer', $a->getAttribute('rel'), $a->getAttribute('href'));
                $this->assertStringContainsString('(abre en una pestaña nueva)', $a->textContent, $a->getAttribute('href'));
            }
        }
    }

    public function test_redes_y_contacto_salen_de_una_sola_fuente_y_aparecen_en_barra_y_pie(): void
    {
        $redes = config('sedeq.redes');
        $this->assertCount(6, $redes);
        $this->assertSame(count($redes), count(array_unique(array_column($redes, 'url'))), 'cada URL se define una vez');
        $this->assertNotContains('https://www.queretaro.gob.mx/covid19', array_column(config('sedeq.enlaces_gobierno'), 'url'));

        $x = $this->dom($this->html('login', false));

        foreach ($redes as $red) {
            $this->assertSame(1, $x->query('//header//a[@href="'.$red['url'].'"]')->length, 'barra: '.$red['url']);
            $this->assertSame(1, $x->query('//footer//a[@href="'.$red['url'].'"]')->length, 'pie: '.$red['url']);
        }

        foreach (config('sedeq.enlaces_gobierno') as $enlace) {
            $this->assertSame(1, $x->query('//header//a[@href="'.$enlace['url'].'"]')->length, $enlace['url']);
        }

        $pie = $x->query('//footer')->item(0)->textContent;
        $this->assertStringContainsString(config('sedeq.contacto.direccion'), $pie);
        foreach (config('sedeq.contacto.telefonos') as $telefono) {
            $this->assertStringContainsString($telefono, $pie);
        }
        $this->assertStringContainsString('Atención ciudadana', $pie);
        $this->assertStringContainsString('Web master', $pie);
        $this->assertStringContainsString('PODER EJECUTIVO DEL ESTADO DE QUERÉTARO Copyright © '.date('Y').' Derechos Reservados.', $pie);
        $this->assertSame(1, $x->query('//footer//a[@href="'.config('sedeq.aviso_privacidad').'"]')->length);
    }

    /** Ajuste visual del pie al portal estatal (2026-10-06): onda propia, columnas cortas, iconos grandes decorativos. */
    public function test_el_pie_sigue_el_portal_estatal(): void
    {
        $x = $this->dom($this->html('login', false));

        $titulos = array_map(fn ($h3) => trim($h3->textContent), iterator_to_array($x->query('//footer//h3')));
        $this->assertSame(['Dirección', 'Teléfono', 'Atención ciudadana', 'Web master'], $titulos);
        $this->assertStringContainsString(config('sedeq.institucion'), $x->query('//footer')->item(0)->textContent);

        // Onda dibujada en línea (no bg-footer.png ni degradado): una sola, decorativa.
        $this->assertSame(1, $x->query('//footer//svg[@data-onda][@aria-hidden="true"][@focusable="false"]')->length);
        $this->assertStringNotContainsString('bg-gradient', file_get_contents(resource_path('views/components/shell/pie.blade.php')));

        // Iconos de contacto de 64 px, decorativos (el h3 nombra la columna).
        $iconos = $x->query('//footer//h3/preceding-sibling::svg[@aria-hidden="true"][contains(concat(" ", @class, " "), " h-16 ")]');
        $this->assertSame(4, $iconos->length);
        $this->assertSame(
            ['pie-ubicacion', 'pie-telefono', 'pie-correo', 'pie-correo'],
            array_map(fn ($svg) => $svg->getAttribute('data-icono'), iterator_to_array($iconos)),
        );

        // «Aviso de privacidad» con subrayado permanente (1.4.1: no se distingue solo por color), más marcado al pasar el cursor.
        $clases = ' '.$x->query('//footer//a[@href="'.config('sedeq.aviso_privacidad').'"]')->item(0)->getAttribute('class').' ';
        $this->assertStringContainsString(' underline ', $clases);
        $this->assertStringContainsString(' hover:decoration-white ', $clases);
    }

    public function test_las_redes_tienen_icono_chico_en_la_barra_y_grande_en_el_pie(): void
    {
        $x = $this->dom($this->html('login', false));

        foreach (config('sedeq.redes') as $red) {
            foreach (['header' => ' h-5 w-5 ', 'footer' => ' h-6 w-6 '] as $zona => $tamano) {
                $svg = $x->query('//'.$zona.'//a[@href="'.$red['url'].'"]/svg')->item(0);
                $this->assertInstanceOf(DOMElement::class, $svg, $zona.': '.$red['url']);
                $this->assertStringContainsString($tamano, ' '.$svg->getAttribute('class').' ', $zona.': '.$red['url']);
            }
        }
    }

    public function test_los_recursos_graficos_estan_alojados_en_el_proyecto(): void
    {
        $this->assertFileExists(public_path('img/layout_set_logo.png'));
        $this->assertFileExists(public_path('img/heraldicas.png'));
    }
}
