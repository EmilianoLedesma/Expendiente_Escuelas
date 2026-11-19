{{-- Revisión del proyecto educativo: barra lateral de 20 secciones (plegable bajo lg) y una sola sección visible; las 20 están en el DOM (cambio en el cliente). El supervisor solo lee. --}}
@php
    $tag = ['aceptado' => ['completado', 'Aceptada', 'text-success-ink', 'check-circle'], 'rechazado' => ['error', 'Rechazada', 'text-error-ink', 'exclamation-circle'], 'pendiente' => ['pendiente', 'Pendiente', 'text-info-ink', 'minus-circle']];
    $total = count($revision['secciones']);
@endphp
{{-- x-data constante: la pestaña activa vive en Alpine (se lee del hash o de data-inicial solo al arrancar), así que re-renderizar tras decidir no la reinicia. --}}
<div data-inicial="{{ $inicial }}" data-total="{{ $total }}" class="lg:ml-[calc(50%-min(55rem,(100vw-3rem)/2))] lg:w-[min(110rem,calc(100vw-3rem))]"
     x-data="{ n: 1, total: 1, menu: false,
        init() {
            this.total = +this.$el.dataset.total;
            const m = /^#seccion-(\d+)$/.exec(location.hash), h = m ? +m[1] : 0;
            this.n = h >= 1 && h <= this.total ? h : +this.$el.dataset.inicial;
            this.$watch('n', v => history.replaceState(null, '', '#seccion-' + v));
        },
        ir(v, enfocar) {
            this.n = Math.min(this.total, Math.max(1, v)); if (! enfocar) { this.menu = false; }
            this.$nextTick(() => {
                const t = document.getElementById('pestana-seccion-' + this.n);
                if (enfocar) { t?.focus(); } else { document.getElementById('panel-seccion-' + this.n)?.scrollIntoView({ block: 'start' }); }
            });
        } }">
    <x-ui.page-header :title="$revision['escuela']" :eyebrow="'Proyecto educativo · '.$revision['nivel']" :back-href="$volver" back-label="Volver" />

    <div class="grid grid-cols-1 gap-md lg:grid-cols-[14rem_minmax(0,1fr)] xl:grid-cols-[15rem_minmax(0,1fr)] 2xl:grid-cols-[16rem_minmax(0,1fr)]">
        <nav aria-label="Secciones del proyecto educativo" class="min-w-0 lg:sticky lg:top-md lg:max-h-[calc(100vh-2rem)] lg:self-start lg:overflow-y-auto">
            <p class="mb-sm text-body-sm font-semibold tabular-nums text-muted">Avance: {{ $revision['revisadas'] }} de {{ $revision['total'] }}</p>

            {{-- Menos de lg: bloque plegable con la sección actual; desde lg la lista siempre se ve. --}}
            <button type="button" id="secciones-menu" x-on:click="menu = ! menu" x-bind:aria-expanded="menu" aria-controls="secciones-lista"
                    class="mb-xs flex min-h-11 w-full items-center justify-between gap-sm rounded-lg border border-hairline bg-canvas p-sm text-left font-semibold text-ink lg:hidden">
                <span class="min-w-0 truncate">Secciones: <span class="tabular-nums" x-text="n"></span> de {{ $total }}</span>
                <span aria-hidden="true" x-text="menu ? '▲' : '▼'">▼</span>
            </button>

            <div id="secciones-lista" role="tablist" aria-orientation="vertical" aria-label="Secciones del proyecto educativo" class="space-y-xs lg:block" x-bind:class="menu ? 'block' : 'hidden lg:block'" x-cloak
                 x-on:keydown.down.prevent="ir(n + 1, true)" x-on:keydown.up.prevent="ir(n - 1, true)"
                 x-on:keydown.home.prevent="ir(1, true)" x-on:keydown.end.prevent="ir(total, true)">
                @foreach ($revision['secciones'] as $s)
                    <button type="button" role="tab" id="pestana-seccion-{{ $s['numero'] }}" aria-controls="panel-seccion-{{ $s['numero'] }}"
                            x-on:click="n = {{ $s['numero'] }}; menu = false" x-bind:aria-selected="n === {{ $s['numero'] }}" x-bind:aria-current="n === {{ $s['numero'] }} ? 'step' : false" x-bind:tabindex="n === {{ $s['numero'] }} ? 0 : -1"
                            x-bind:class="n === {{ $s['numero'] }} ? 'border-2 border-primary' : 'border border-hairline'"
                            class="flex min-h-11 w-full cursor-pointer items-start justify-between gap-xs rounded-lg bg-canvas px-sm py-xs text-left transition-colors duration-150 hover:bg-surface-soft motion-reduce:transition-none">
                        <span class="min-w-0 break-words text-body-sm font-semibold leading-snug text-ink"><span class="tabular-nums">{{ $s['numero'] }}.</span> {{ $s['titulo'] }}</span>
                        <span class="mt-px shrink-0 {{ $tag[$s['estado']][2] }}" title="{{ $tag[$s['estado']][1] }}"><x-ui.icon :nombre="$tag[$s['estado']][3]" class="h-5 w-5" /><span class="sr-only">{{ $tag[$s['estado']][1] }}</span></span>
                    </button>
                @endforeach
            </div>
        </nav>

        <div class="min-w-0">

    @foreach ($revision['secciones'] as $s)
        @php
            $contenido = $contenidos[$s['slug']];
            $actual = $s['slug'] === $seccionAbierta;
        @endphp
        <div role="tabpanel" id="panel-seccion-{{ $s['numero'] }}" aria-labelledby="pestana-seccion-{{ $s['numero'] }}" wire:key="panel-{{ $s['clave'] }}"
             x-show="n === {{ $s['numero'] }}" x-cloak class="scroll-mt-md 2xl:grid 2xl:grid-cols-[minmax(0,1fr)_20rem] 2xl:gap-md">
            <div class="min-w-0">
            @unless ($s['completa'])
                <x-ui.alert tipo="warning" class="mb-md">El solicitante todavía no completa esta sección.</x-ui.alert>
            @endunless

            <x-ui.section :title="$s['numero'].'. '.$contenido['titulo']" class="!mt-0">
                @if ($contenido['filas'] !== [])
                    @include('livewire.sedeq.partials.proyecto-campos', ['filas' => $contenido['filas']])
                @endif

                @foreach ($contenido['listas'] as $lista)
                    <div class="space-y-md">
                        <h3 class="text-body-md font-semibold text-ink">{{ $lista['titulo'] }}</h3>
                        @forelse ($lista['filas'] as $fila)
                            <div class="rounded-md border border-hairline px-md">
                                @include('livewire.sedeq.partials.proyecto-campos', ['filas' => $fila])
                            </div>
                        @empty
                            <p class="text-body-md text-muted">Sin captura.</p>
                        @endforelse
                    </div>
                @endforeach

                @if ($contenido['descargas'] !== [])
                    <div class="space-y-xs">
                        <h3 class="text-body-md font-semibold text-ink">Archivos PDF</h3>
                        <ul class="space-y-xs">
                            @foreach ($contenido['descargas'] as $d)
                                <li>
                                    <a href="{{ route('sedeq.proyecto-archivo', ['escuelaNivel' => $escuelaNivelId, 'clave' => $d['clave']]) }}" target="_blank" rel="noopener" class="font-semibold text-primary underline">{{ $d['etiqueta'] }} (PDF)</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.section>
            </div>

            {{-- Decisión: a la derecha y fija desde xl; debajo del contenido en pantallas menores. Anterior/Siguiente van al pie de este bloque. --}}
            <aside aria-label="Decisión de la sección {{ $s['numero'] }}" class="mt-md grid gap-md self-start lg:grid-cols-2 2xl:sticky 2xl:top-md 2xl:mt-0 2xl:flex 2xl:flex-col">
            <div class="rounded-lg border border-hairline bg-surface-card p-md">
                <x-ui.status-tag :estado="$tag[$s['estado']][0]" :texto="$tag[$s['estado']][1]" />
            @if ($s['estado'] !== 'pendiente')
                <p class="mt-xs max-w-prose whitespace-pre-line break-words text-body-md leading-relaxed text-muted">
                    Decisión vigente: {{ $s['estado'] === 'aceptado' ? 'aceptada' : 'rechazada' }} por {{ $s['por'] }} el {{ $s['cuando'] }}.
                    @if (filled($s['motivo']))
                        Motivo: {{ $s['motivo'] }}
                    @endif
                </p>
            @endif
            </div>

            @if ($revision['editable'])
                {{-- Motivo local al panel (x-model): cambiar de sección no lo pierde; se envía al servidor solo al rechazar. --}}
                <x-ui.section title="Decisión sobre esta sección" x-data="{ motivo: '' }" class="!mt-0">
                    <div>
                        <label for="motivo-{{ $s['numero'] }}" class="block text-body-md font-semibold text-ink">Motivo del rechazo</label>
                        <p id="motivo-{{ $s['numero'] }}-hint" class="mt-xxs text-body-sm text-muted">Obligatorio solo para rechazar.</p>
                        @if ($actual)
                            @error('motivo')
                                <p id="motivo-{{ $s['numero'] }}-error" class="mt-xxs flex items-start gap-xxs text-body-sm font-semibold text-error-ink">
                                    <x-ui.icon nombre="exclamation-circle" class="mt-px h-5 w-5" />
                                    <span><span class="sr-only">Error:</span> {{ $message }}</span>
                                </p>
                            @enderror
                        @endif
                        <div class="mt-xs">
                            <x-ui.textarea id="motivo-{{ $s['numero'] }}" x-model="motivo" aria-describedby="motivo-{{ $s['numero'] }}-hint" />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-sm">
                        <x-ui.button-primary type="button" x-on:click="$wire.aceptar('{{ $s['slug'] }}')">Aceptar sección</x-ui.button-primary>
                        <x-ui.button-secondary x-on:click="$wire.set('motivo', motivo, false); $wire.rechazar('{{ $s['slug'] }}')">Rechazar sección</x-ui.button-secondary>
                    </div>
                </x-ui.section>
            @elseif ($revision['estado'] === 'con_observaciones')
                <p class="text-body-md text-muted lg:col-span-2">Este trámite está con el solicitante para corregir; podrás decidir cuando lo reenvíe.</p>
            @endif

            <div class="flex items-center justify-between gap-sm border-t border-hairline pt-md lg:col-span-2">
                @if ($s['numero'] > 1)
                    <x-ui.button-secondary x-on:click="ir({{ $s['numero'] - 1 }})">Anterior</x-ui.button-secondary>
                @else
                    <span></span>
                @endif
                @if ($s['numero'] < $total)
                    <x-ui.button-primary type="button" x-on:click="ir({{ $s['numero'] + 1 }})">Siguiente</x-ui.button-primary>
                @endif
            </div>
            </aside>
        </div>
    @endforeach
        </div>
    </div>
</div>
