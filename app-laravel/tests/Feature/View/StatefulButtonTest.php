<?php

namespace Tests\Feature\View;

use Tests\TestCase;

class StatefulButtonTest extends TestCase
{
    private function boton(string $atributos = ''): string
    {
        return $this->blade('<x-ui.stateful-button action="enviarAlgo" label="Enviar correo" '.$atributos.' />')->__toString();
    }

    public function test_renderiza_con_valores_por_defecto(): void
    {
        $html = $this->boton();

        $this->assertStringContainsString('Enviar correo', $html);
        $this->assertStringContainsString('Enviando...', $html);
        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('wire:ignore', $html);
    }

    public function test_acepta_etiquetas_personalizadas_para_cada_estado(): void
    {
        $html = $this->boton('loading-label="Mandando" success-label="Solicitud de envío realizada" error-label="Falló el envío"');

        foreach (['Mandando', 'Solicitud de envío realizada', 'Falló el envío'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
    }

    public function test_usa_los_tokens_del_sistema_y_no_colores_ajenos(): void
    {
        $html = $this->boton();

        foreach (['bg-primary', 'text-on-primary', 'hover:bg-primary-active', 'bg-success-ink', 'bg-error-ink', 'rounded-md', 'min-h-11', 'focus-visible:outline-2'] as $clase) {
            $this->assertStringContainsString($clase, $html);
        }
        $this->assertStringNotContainsString('green-500', $html);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b/', $html);
    }

    public function test_llama_a_la_accion_de_livewire_indicada_y_espera_el_resultado(): void
    {
        $html = $this->boton();

        $this->assertStringContainsString('$wire.$call', $html);
        $this->assertStringContainsString('enviarAlgo', $html);
        $this->assertStringContainsString('await', $html);
        $this->assertStringContainsString('=== true', $html);
        $this->assertStringContainsString('x-on:click.prevent', $html);
    }

    public function test_evita_envios_duplicados_sin_perder_el_foco(): void
    {
        $html = $this->boton();

        $this->assertStringContainsString("estado === 'loading'", $html);
        $this->assertStringContainsString('aria-disabled', $html);
        $this->assertStringNotContainsString('wire:loading.attr="disabled"', $html);
    }

    public function test_el_spinner_gira_sin_parar_y_usa_la_geometria_original(): void
    {
        $html = $this->boton();

        $this->assertStringContainsString('animate-spin', $html);
        $this->assertStringContainsString('M12 3a9 9 0 1 0 9 9', $html);
        $this->assertStringContainsString('w-5', $html);
        $this->assertStringContainsString('scale-0', $html);
        $this->assertStringContainsString("estado === 'loading'", $html);
    }

    public function test_el_icono_de_exito_solo_existe_con_el_estado_success_y_el_de_error_con_error(): void
    {
        $html = $this->boton();

        $this->assertStringContainsString('m9 12 2 2 4-4', $html);
        $this->assertStringContainsString("estado === 'success'", $html);
        $this->assertStringContainsString("estado === 'error'", $html);
        $this->assertStringContainsString('=== true', $html);
    }

    public function test_un_resultado_distinto_de_true_o_una_excepcion_nunca_es_exito(): void
    {
        $html = $this->boton();

        $this->assertStringContainsString("'error'", $html);
        $this->assertStringContainsString('catch', $html);
    }

    public function test_permite_reintentar_desde_el_estado_de_error(): void
    {
        $html = $this->boton();

        $this->assertStringNotContainsString("estado === 'error') return", $html);
        $this->assertStringContainsString("estado === 'loading'", $html);
    }

    public function test_cada_instancia_tiene_su_propio_estado(): void
    {
        $html = $this->blade('<x-ui.stateful-button action="a" label="Uno" /><x-ui.stateful-button action="b" label="Dos" />')->__toString();

        $this->assertSame(2, substr_count($html, 'x-data='));
        $this->assertSame(2, substr_count($html, "estado: 'idle'"));
        $this->assertStringNotContainsString('window.', $html);
        $this->assertStringNotContainsString('.window', $html);
    }

    public function test_es_accesible_y_respeta_movimiento_reducido(): void
    {
        $html = $this->boton();

        $this->assertStringContainsString(':aria-busy', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('sr-only', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('motion-reduce:animate-none', $html);
        $this->assertStringContainsString('motion-reduce:transition-none', $html);
    }

    public function test_el_reinicio_automatico_solo_existe_si_se_configura(): void
    {
        $this->assertStringContainsString('resetAfter: null', $this->boton());
        $this->assertStringContainsString('resetAfter: 4000', $this->boton('reset-after="4000"'));
    }

    public function test_admite_deshabilitado_tipo_submit_y_clases_extra(): void
    {
        $html = $this->boton('disabled type="submit" class="mt-md"');

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('mt-md', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*\sdisabled/', $html);
    }
}
