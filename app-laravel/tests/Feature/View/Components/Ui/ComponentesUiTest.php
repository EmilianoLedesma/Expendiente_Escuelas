<?php

namespace Tests\Feature\View\Components\Ui;

use Tests\TestCase;

class ComponentesUiTest extends TestCase
{
    public function test_field_con_input_asocia_etiqueta_ayuda_y_control(): void
    {
        $view = $this->blade(
            '<x-ui.field id="calle" label="Calle y número" hint="Sin abreviaturas"><x-ui.input wire:model.blur="calle" /></x-ui.field>'
        );

        $view->assertSee('for="calle"', false)
            ->assertSee('id="calle"', false)
            ->assertSee('id="calle-hint"', false)
            ->assertSee('aria-describedby="calle-hint"', false)
            ->assertSee('wire:model.blur="calle"', false)
            ->assertSee('border-control', false)
            ->assertSee('min-h-11', false)
            ->assertDontSee('aria-invalid', false);
    }

    public function test_field_con_error_lo_anuncia_y_lo_liga_al_control(): void
    {
        $view = $this->withViewErrors(['calle' => 'El campo calle es obligatorio.'])
            ->blade('<x-ui.field id="calle" label="Calle y número"><x-ui.input wire:model.blur="calle" /></x-ui.field>');

        $view->assertSee('id="calle-error"', false)
            ->assertSee('El campo calle es obligatorio.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="calle-error"', false)
            ->assertSee('border-error-ink', false);
    }

    public function test_field_opcional_lo_indica_en_la_etiqueta(): void
    {
        $this->blade('<x-ui.field id="numeroInt" label="Número interior" optional><x-ui.input /></x-ui.field>')
            ->assertSee('(opcional)');
    }

    public function test_input_suelto_usa_su_propio_id_y_muestra_la_unidad(): void
    {
        $this->blade('<x-ui.input id="espacios.3.superficieM2" type="number" inputmode="decimal" unit="m²" />')
            ->assertSee('id="espacios.3.superficieM2"', false)
            ->assertSee('inputmode="decimal"', false)
            ->assertSee('m²');
    }

    public function test_select_y_textarea_heredan_el_id_del_field(): void
    {
        $this->blade('<x-ui.field id="plantelId" label="Plantel"><x-ui.select wire:model.blur="plantelId"><option value="">Selecciona…</option></x-ui.select></x-ui.field>')
            ->assertSee('<select', false)
            ->assertSee('id="plantelId"', false);

        $this->blade('<x-ui.field id="acreditacionForm.observaciones" label="Observaciones" optional><x-ui.textarea wire:model="acreditacionForm.observaciones" /></x-ui.field>')
            ->assertSee('<textarea', false)
            ->assertSee('id="acreditacionForm.observaciones"', false);
    }

    public function test_radio_group_usa_fieldset_legend_y_descripciones(): void
    {
        $view = $this->blade(
            '<x-ui.radio-group id="bifurcacion" legend="¿Para qué plantel?" wire:model.live="bifurcacion" :opciones="$o" />',
            ['o' => ['nuevo' => ['etiqueta' => 'Plantel nuevo', 'descripcion' => 'Primer trámite'], 'existente' => 'Plantel registrado']]
        );

        $view->assertSee('<fieldset id="bifurcacion"', false)
            ->assertSee('<legend', false)
            ->assertSee('for="bifurcacion-nuevo"', false)
            ->assertSee('id="bifurcacion-existente"', false)
            ->assertSee('Primer trámite')
            ->assertSee('wire:model.live="bifurcacion"', false)
            ->assertSee('min-h-11', false);
    }

    public function test_checkbox_tiene_etiqueta_con_area_de_toque_de_44px(): void
    {
        $this->blade('<x-ui.checkbox id="tieneAstaBandera" wire:model.blur="tieneAstaBandera">Cuenta con asta bandera</x-ui.checkbox>')
            ->assertSee('for="tieneAstaBandera"', false)
            ->assertSee('type="checkbox"', false)
            ->assertSee('min-h-11', false)
            ->assertSee('Cuenta con asta bandera');
    }

    public function test_error_summary_no_se_muestra_sin_errores(): void
    {
        $this->blade('<x-ui.error-summary />')->assertDontSee('Revisa los siguientes datos');
    }

    public function test_error_summary_enlaza_cada_error_y_recibe_el_foco(): void
    {
        $view = $this->withViewErrors(['calle' => 'Falta la calle.', 'vigencia' => 'Vencido.'])
            ->blade('<x-ui.error-summary :excluir="[\'vigencia\']" />');

        $view->assertSee('Revisa los siguientes datos')
            ->assertSee('role="alert"', false)
            ->assertSee('tabindex="-1"', false)
            ->assertSee('x-init="$el.focus()"', false)
            ->assertSee('href="#calle"', false)
            ->assertSee('Falta la calle.')
            ->assertDontSee('Vencido.');
    }

    public function test_status_tag_muestra_texto_por_estado(): void
    {
        foreach ([
            'completado' => 'Completado',
            'en_curso' => 'En curso',
            'pendiente' => 'Pendiente',
            'no_disponible' => 'No disponible aún',
            'no_aplica' => 'No aplica para este nivel',
            'error' => 'Requiere corrección',
        ] as $estado => $texto) {
            $this->blade('<x-ui.status-tag :estado="$e" />', ['e' => $estado])->assertSee($texto);
        }

        $this->blade('<x-ui.status-tag estado="en_curso" texto="En captura" />')->assertSee('En captura')->assertDontSee('En curso');
    }

    public function test_summary_list_usa_dl_y_guion_para_vacios(): void
    {
        $this->blade('<x-ui.summary-list :filas="$f" />', ['f' => ['Colonia' => 'Centro', 'Localidad' => null]])
            ->assertSee('<dl', false)
            ->assertSee('<dt', false)
            ->assertSee('Centro')
            ->assertSee('—');
    }

    public function test_alert_de_error_es_role_alert_con_titulo(): void
    {
        $this->blade('<x-ui.alert tipo="error" titulo="No se pudo guardar">Detalle</x-ui.alert>')
            ->assertSee('role="alert"', false)
            ->assertSee('No se pudo guardar')
            ->assertSee('Detalle');

        $this->blade('<x-ui.alert tipo="success">Listo</x-ui.alert>')->assertSee('role="status"', false);
    }

    public function test_action_bar_evita_doble_envio_y_ofrece_volver(): void
    {
        $this->blade('<x-ui.action-bar accion="guardar" back-href="/tramite/1" />')
            ->assertSee('Guardar y continuar')
            ->assertSee('Guardando…')
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertSee('wire:target="guardar"', false)
            ->assertSee('href="/tramite/1"', false)
            ->assertSee('Volver al resumen');
    }

    public function test_page_header_muestra_regreso_eyebrow_y_titulo(): void
    {
        $this->blade('<x-ui.page-header back-href="/tramite/1" eyebrow="Paso 2 de 4 · Responsable legal" title="Responsable legal"><x-slot:intro>Intro</x-slot:intro></x-ui.page-header>')
            ->assertSee('href="/tramite/1"', false)
            ->assertSee('Resumen del trámite')
            ->assertSee('Paso 2 de 4 · Responsable legal')
            ->assertSee('<h1', false)
            ->assertSee('font-display', false)
            ->assertSee('Intro');
    }

    public function test_section_titula_con_h2(): void
    {
        $this->blade('<x-ui.section title="Domicilio del plantel">x</x-ui.section>')
            ->assertSee('<h2', false)
            ->assertSee('Domicilio del plantel');
    }

    public function test_botones_miden_44px_y_se_vuelven_enlace_con_href(): void
    {
        $this->blade('<x-ui.button-primary href="/tramite/preregistro">Iniciar nuevo trámite</x-ui.button-primary>')
            ->assertSee('<a href="/tramite/preregistro"', false)
            ->assertSee('min-h-11', false);

        $this->blade('<x-ui.button-secondary>Reemplazar</x-ui.button-secondary>')
            ->assertSee('type="button"', false)
            ->assertSee('min-h-11', false);
    }

    public function test_los_iconos_son_decorativos(): void
    {
        $this->blade('<x-ui.icon nombre="check-circle" class="h-5 w-5" />')
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('<path', false);
    }

    public function test_action_bar_queda_fija_desde_sm(): void
    {
        $this->blade('<x-ui.action-bar accion="guardar" />')
            ->assertSee('sm:sticky', false)
            ->assertSee('sm:bottom-0', false);
    }
}
