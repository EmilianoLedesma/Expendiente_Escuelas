{{-- Sub-pasos dentro de un mismo formulario: un panel visible a la vez (Alpine, sin viaje al servidor).
     Todos los paneles siguen en el DOM, así que wire:model y los #ancla de x-ui.error-summary funcionan igual.
     Uso: <x-ui.stepper :pasos="['A','B']"> <x-ui.stepper-panel :indice="0" :total="2" titulo="A">…</x-ui.stepper-panel> … </x-ui.stepper> --}}
{{-- $completos (opcional): lista paralela a $pasos de booleanos calculados en el servidor. Sin ella, la marca "con datos" sale de los campos del panel. --}}
@props(['pasos', 'completos' => null, 'compacto' => false])

<div
    {{ $attributes }}
    x-data="{
        paso: 0,
        total: {{ count($pasos) }},
        hechos: {},
        ir(i) {
            this.paso = Math.max(0, Math.min(this.total - 1, i));
            this.$nextTick(() => this.$refs.nav && this.$refs.nav.scrollIntoView({ block: 'nearest' }));
        },
        marcar() {
            this.$root.querySelectorAll('[data-paso]').forEach((panel) => {
                this.hechos[panel.dataset.paso] = [...panel.querySelectorAll('input, select, textarea')]
                    .some((c) => (c.type === 'checkbox' ? c.checked : c.value.trim() !== ''));
            });
        },
        aCampo(id, enfocar) {
            const campo = id ? document.getElementById(id) : null;
            const panel = campo ? campo.closest('[data-paso]') : null;
            if (!panel || !this.$root.contains(panel)) return false;
            this.paso = Number(panel.dataset.paso);
            if (enfocar) this.$nextTick(() => campo.focus());
            return true;
        },
        alClic(e) {
            const enlace = e.target.closest('[role=alert] a[href^=&quot;#&quot;]');
            if (enlace && this.aCampo(enlace.getAttribute('href').slice(1), true)) e.preventDefault();
        },
        alEnter(e) {
            if (e.target.tagName !== 'INPUT' || e.target.form !== this.$root.closest('form') || this.paso >= this.total - 1) return;
            e.preventDefault();
            this.ir(this.paso + 1);
        },
    }"
    x-init="marcar()"
    x-on:input="marcar()"
    x-on:change="marcar()"
    x-on:keydown.enter="alEnter($event)"
    x-on:click.window="alClic($event)"
    x-on:stepper-ir-a.window="aCampo($event.detail.id, $event.detail.enfocar)"
>
    @if (count($pasos) > 1)
        <nav x-ref="nav" data-stepper-nav aria-label="Pasos de la captura" class="mb-lg">
            <ol class="flex flex-wrap gap-xs">
                @foreach ($pasos as $i => $etiqueta)
                    <li>
                        <button
                            type="button"
                            data-paso-boton="{{ $i }}"
                            @if ($compacto) title="{{ $etiqueta }}" @endif
                            x-on:click="ir({{ $i }})"
                            x-bind:aria-current="paso === {{ $i }} ? 'step' : false"
                            x-bind:class="paso === {{ $i }} ? 'border-primary bg-primary text-on-primary' : 'border-hairline bg-canvas text-primary hover:bg-surface-soft'"
                            class="inline-flex min-h-11 items-center gap-xs rounded-md border px-md text-body-sm font-semibold transition-colors duration-150"
                        >
                            @if ($compacto)
                                {{-- Muchos pasos: solo el número en pantalla; el nombre queda para lectores de pantalla y el tooltip. --}}
                                <span aria-hidden="true">{{ $i + 1 }}</span>
                                <span class="sr-only">{{ $i + 1 }}. {{ $etiqueta }}</span>
                            @else
                                <span>{{ $i + 1 }}. {{ $etiqueta }}</span>
                            @endif
                            @if ($completos === null)
                                <span x-show="hechos[{{ $i }}]" x-cloak>
                                    <x-ui.icon nombre="check" class="h-4 w-4" />
                                    <span class="sr-only">(con datos)</span>
                                </span>
                            @elseif ($completos[$i] ?? false)
                                <x-ui.icon nombre="check" class="h-4 w-4" />
                                <span class="sr-only">(paso completo)</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    {{ $slot }}
</div>
