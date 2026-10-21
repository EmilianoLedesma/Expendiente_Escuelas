{{-- Tablero del supervisor (opción A). Asignar = marcar filas + "Asignar a…" (operable con teclado; no hay arrastrar). --}}
@php($prioridades = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'])
{{-- Cambiar la prioridad reordena la fila (y en Windows cada flecha sobre el select cerrado dispara change):
     tras cada cambio se devuelve el foco al mismo select para no perder el lugar del teclado (WCAG 2.4.3). --}}
<div x-data x-on:prioridad-cambiada.window="$nextTick(() => document.getElementById('prioridad-' + $event.detail.id)?.focus())">
    <x-ui.page-header title="Tablero del supervisor" eyebrow="Revisión SEDEQ">
        <x-slot:intro>Asigna los trámites en revisión a los revisores y vigila la carga de cada uno.</x-slot:intro>
    </x-ui.page-header>

    {{-- Resumen de carga: quién tiene lugar al asignar. "llena" va en texto además del color. La gestión vive en Revisores. --}}
    @if ($revisores !== [])
        <ul aria-label="Carga de los revisores" class="mb-lg flex flex-wrap gap-x-md gap-y-xxs text-body-sm">
            @foreach ($revisores as $r)
                @php($llena = $r->asignados >= $r->limite)
                <li wire:key="carga-{{ $r->revisorId }}" @class(['tabular-nums', 'font-semibold text-error-ink' => $llena, 'text-muted' => ! $llena])>{{ $r->nombre }} {{ $r->asignados }}/{{ $r->limite }}{{ $llena ? ' · llena' : '' }}</li>
            @endforeach
        </ul>
    @endif

    {{-- Confirmación de tope: <dialog> modal (foco atrapado, Escape cancela, el foco vuelve a quien abrió). --}}
    @if ($sobrecarga !== [])
        <dialog id="sobrecarga" wire:key="sobrecarga-{{ $avisosSobrecarga }}" aria-labelledby="sobrecarga-titulo" x-data="{ confirmando: false }" x-init="$el.showModal()"
                x-on:close="if (! confirmando) $wire.cancelarSobrecarga()" onclick="if (event.target === this) this.close()"
                class="modal rounded-lg border border-hairline bg-canvas p-lg text-left text-body-md text-ink">
            <h2 id="sobrecarga-titulo" class="text-title-md font-semibold text-ink">Tope de trámites alcanzado</h2>
            <p class="mt-sm">{{ $sobrecargado?->nombre ?? 'El revisor' }} ya tiene {{ $sobrecarga['limite'] }} trámites asignados. Si confirmas, se le quitan los que hagan falta (primero los de menor avance y menor prioridad) y regresan a "Sin asignar"; su avance de revisión se conserva.</p>
            <div class="mt-lg flex flex-wrap justify-end gap-sm">
                <button type="button" autofocus onclick="this.closest('dialog').close()" class="inline-flex min-h-11 items-center justify-center rounded-md border border-control bg-canvas px-lg text-body-md font-semibold text-ink transition duration-150 hover:border-ink hover:bg-surface-card active:bg-surface-soft">Cancelar</button>
                <button type="button" x-on:click="confirmando = true; $el.closest('dialog').close()"
                        wire:click="asignarSeleccion({{ json_encode($sobrecarga['ids']) }}, {{ $sobrecarga['revisorId'] }}, true)" wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-lg text-body-md font-semibold text-on-primary transition duration-150 hover:brightness-90 hover:shadow-md active:brightness-75">Asignar de todos modos</button>
            </div>
        </dialog>
    @endif
    <div>
        <x-ui.section title="Trámites en revisión" class="!mt-0">
            @if ($filas === [])
                <p class="text-body-md text-muted">No hay trámites en revisión.</p>
            @else
                <p class="text-body-sm text-muted">{{ count($filas) }} en revisión · {{ collect($filas)->whereNull('revisorId')->count() }} sin asignar. Ordenados por prioridad y tiempo de espera.</p>

                <div class="flex flex-wrap items-end gap-sm">
                    <x-ui.field id="revisorDestino" label="Asignar a…" class="min-w-56 flex-1">
                        <x-ui.select wire:model="revisorDestino">
                            <option value="">Selecciona un revisor…</option>
                            @foreach ($revisores as $r)
                                <option value="{{ $r->revisorId }}">{{ $r->nombre }} ({{ $r->asignados }} de {{ $r->limite }})</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.button-primary type="button" wire:loading.attr="disabled"
                        x-on:click="$wire.asignarSeleccion($wire.seleccion, Number($wire.revisorDestino) || 0)">
                        Asignar seleccionados
                    </x-ui.button-primary>
                </div>
                @error('seleccion')
                    <p id="seleccion-error" class="flex items-start gap-xxs text-body-sm font-semibold text-error-ink">
                        <x-ui.icon nombre="exclamation-circle" class="mt-px h-5 w-5" />
                        <span><span class="sr-only">Error:</span> {{ $message }}</span>
                    </p>
                @enderror

                <div class="overflow-x-auto rounded-lg border border-hairline">
                    <table class="w-full text-left text-body-sm">
                        <caption class="sr-only">Trámites en revisión, ordenados por prioridad y tiempo de espera</caption>
                        <thead class="bg-surface-soft text-caption uppercase text-muted">
                            <tr>
                                <th scope="col" class="w-px px-sm py-sm"><span class="sr-only">Seleccionar</span></th>
                                <th scope="col" class="px-sm py-sm font-semibold">N.º</th>
                                <th scope="col" class="px-sm py-sm font-semibold">Plantel</th>
                                <th scope="col" class="px-sm py-sm font-semibold">Prioridad</th>
                                <th scope="col" class="px-sm py-sm font-semibold">Espera</th>
                                <th scope="col" class="px-sm py-sm font-semibold">Revisor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @foreach ($filas as $fila)
                                {{-- Clic en la fila alterna la casilla (que sigue siendo el control real); los controles interactivos no alternan. --}}
                                <tr wire:key="fila-{{ $fila->escuelaNivelId }}" x-data
                                    x-on:click="if (! $event.target.closest('a, button, select, input, label')) $refs.chk.click()"
                                    class="cursor-pointer transition-colors duration-150 hover:bg-surface-soft has-[input[type=checkbox]:checked]:bg-surface-soft motion-reduce:transition-none">
                                    <td class="px-sm py-xs">
                                        <label for="sel-{{ $fila->escuelaNivelId }}" class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center">
                                            <input type="checkbox" x-ref="chk" id="sel-{{ $fila->escuelaNivelId }}" value="{{ $fila->escuelaNivelId }}" wire:model="seleccion"
                                                   @error('seleccion') aria-describedby="seleccion-error" @enderror
                                                   class="h-5 w-5 rounded-xs border-control text-primary">
                                            <span class="sr-only">Seleccionar trámite {{ $fila->escuelaNivelId }}, {{ $fila->plantel }}, {{ $fila->nivel }}</span>
                                        </label>
                                    </td>
                                    <td class="px-sm py-xs tabular-nums text-muted">{{ $fila->escuelaNivelId }}</td>
                                    <td class="px-sm py-xs">
                                        <a href="{{ route('sedeq.tramite', ['escuela' => $fila->escuelaId]) }}" class="break-words font-semibold text-primary hover:underline">{{ $fila->plantel }}</a>
                                        <span class="block text-caption text-muted">{{ $fila->nivel }}</span>
                                        <a href="{{ route('sedeq.revision', ['escuelaNivel' => $fila->escuelaNivelId]) }}" class="text-caption font-semibold text-primary hover:underline">Revisar</a>
                                    </td>
                                    <td class="px-sm py-xs">
                                        <label for="prioridad-{{ $fila->escuelaNivelId }}" class="sr-only">Prioridad del trámite {{ $fila->escuelaNivelId }}</label>
                                        <select id="prioridad-{{ $fila->escuelaNivelId }}" wire:change="cambiarPrioridad({{ $fila->escuelaNivelId }}, $event.target.value)"
                                                class="block min-h-11 rounded-md border border-control bg-canvas pl-sm pr-10 text-body-sm text-ink">
                                            @foreach ($prioridades as $valor => $etiqueta)
                                                <option value="{{ $valor }}" @selected($fila->prioridad === $valor)>{{ $etiqueta }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="whitespace-nowrap px-sm py-xs tabular-nums">{{ $fila->diasEspera }} {{ $fila->diasEspera === 1 ? 'día' : 'días' }}</td>
                                    <td class="px-sm py-xs">
                                        @if ($fila->revisorNombre === null)
                                            <span class="italic text-muted">Sin asignar</span>
                                        @else
                                            {{ $fila->revisorNombre }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.section>
    </div>
</div>
