{{-- Revisores del supervisor: carga, invitación y revocación (el modal de revocar es un <dialog> por revisor). --}}
<div>
    <x-ui.page-header title="Revisores" eyebrow="Revisión SEDEQ">
        <x-slot:intro>Vigila la carga de cada revisor, invita a nuevos y revoca el acceso cuando haga falta.</x-slot:intro>
    </x-ui.page-header>

    @php($prioridades = ['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'])
    <x-ui.section title="Carga de revisores" class="!mt-0">
        @forelse ($revisores as $r)
            @php($llena = $r->asignados >= $r->limite)
            <div wire:key="revisor-{{ $r->revisorId }}" class="border-b border-hairline pb-sm last:border-0 last:pb-0">
                <div class="flex flex-wrap items-baseline justify-between gap-xs">
                    <span class="break-words font-semibold text-ink">{{ $r->nombre }}</span>
                    {{-- El texto dice "llena": el rojo no es la única señal. --}}
                    <span @class(['text-body-sm tabular-nums', 'font-semibold text-error-ink' => $llena, 'text-muted' => ! $llena])>{{ $r->asignados }} de {{ $r->limite }}{{ $llena ? ' · llena' : '' }}</span>
                </div>
                <div class="mt-xxs h-2 rounded-pill bg-surface-card" aria-hidden="true">
                    <div @class(['h-full rounded-pill', 'bg-error-ink' => $llena, 'bg-primary' => ! $llena]) style="width: {{ $r->limite > 0 ? min(100, intdiv($r->asignados * 100, $r->limite)) : 100 }}%"></div>
                </div>
                <p class="mt-xxs text-caption text-muted">{{ $r->revisadas }} de {{ $r->total }} secciones revisadas</p>

                @php($suyos = $filasPorRevisor[$r->revisorId] ?? [])
                <div x-data="{ abierto: false }">
                    <button type="button" aria-expanded="false" :aria-expanded="abierto" aria-controls="tramites-{{ $r->revisorId }}" x-on:click="abierto = ! abierto"
                            class="mt-xs inline-flex min-h-11 cursor-pointer items-center gap-xs rounded-md px-xs text-body-sm font-semibold text-primary hover:bg-surface-soft">
                        <x-ui.icon nombre="chevron-down" class="h-4 w-4 transition-transform duration-150 motion-reduce:transition-none" x-bind:class="abierto && 'rotate-180'" />
                        Trámites asignados ({{ count($suyos) }})<span class="sr-only"> de {{ $r->nombre }}</span>
                    </button>
                    <div id="tramites-{{ $r->revisorId }}" x-show="abierto" x-cloak class="mt-xxs">
                        @forelse ($suyos as $f)
                            <a wire:key="t-{{ $f->escuelaNivelId }}" href="{{ route('sedeq.tramite', ['escuela' => $f->escuelaId]) }}" class="block min-h-11 rounded-md px-xs py-xxs text-body-sm text-ink hover:bg-surface-soft">
                                <span class="font-semibold">{{ $f->plantel }}</span>
                                <span class="block text-muted">{{ $f->nivel }} · Prioridad {{ $prioridades[$f->prioridad] ?? $f->prioridad }} · espera {{ $f->diasEspera }} {{ $f->diasEspera === 1 ? 'día' : 'días' }}</span>
                            </a>
                        @empty
                            <p class="px-xs text-body-sm text-muted">Sin trámites asignados.</p>
                        @endforelse
                    </div>
                </div>

                <button type="button" onclick="document.getElementById('revocar-{{ $r->revisorId }}').showModal()"
                        class="mt-xs inline-flex min-h-11 cursor-pointer items-center rounded-md px-xs text-body-sm font-semibold text-error-ink hover:bg-error-soft">
                    Revocar<span class="sr-only"> a {{ $r->nombre }}</span>
                </button>

                {{-- showModal() atrapa el foco y Escape cierra; al cerrar, el foco vuelve al botón que lo abrió. --}}
                <dialog id="revocar-{{ $r->revisorId }}" aria-labelledby="revocar-{{ $r->revisorId }}-titulo" onclick="if (event.target === this) this.close()" class="modal rounded-lg border border-error-ink bg-canvas p-lg text-left text-body-md text-ink">
                    <h2 id="revocar-{{ $r->revisorId }}-titulo" class="text-title-md font-semibold text-error-ink">¿Revocar a {{ $r->nombre }}?</h2>
                    <p class="mt-sm">Pierde el acceso a la revisión. Sus trámites asignados regresan a «Sin asignar». Su cuenta no se borra.</p>
                    <div class="mt-lg flex flex-wrap justify-end gap-sm">
                        <button type="button" onclick="this.closest('dialog').close()" class="inline-flex min-h-11 items-center justify-center rounded-md border border-control bg-canvas px-lg text-body-md font-semibold text-ink transition duration-150 hover:border-ink hover:bg-surface-card active:bg-surface-soft">
                            Cancelar<span class="sr-only"> la revocación de {{ $r->nombre }}</span>
                        </button>
                        <button type="button" wire:click="revocar({{ $r->revisorId }})" onclick="this.closest('dialog').close()" class="inline-flex min-h-11 items-center justify-center rounded-md bg-error-ink px-lg text-body-md font-semibold text-on-primary transition duration-150 hover:brightness-90 hover:shadow-md active:brightness-75">
                            Revocar acceso<span class="sr-only"> de {{ $r->nombre }}</span>
                        </button>
                    </div>
                </dialog>
            </div>
        @empty
            <p class="text-body-md text-muted">Aún no hay revisores. Invita al primero.</p>
        @endforelse
    </x-ui.section>

    <x-ui.section title="Invitar revisor">
        <form novalidate>
            <div class="space-y-md">
                <x-ui.field id="nombre" label="Nombre completo">
                    <x-ui.input maxlength="255" wire:model="nombre" autocomplete="off" />
                </x-ui.field>
                <x-ui.field id="correo" label="Correo electrónico" hint="Le llegará un enlace para definir su contraseña.">
                    <x-ui.input type="email" maxlength="255" wire:model="correo" autocomplete="off" />
                </x-ui.field>
            </div>
            <x-ui.stateful-button type="submit" action="invitar" label="+ Invitar revisor" success-label="Invitación enviada" error-label="No se pudo enviar. Reintentar" :reset-after="4000" class="mt-md" />
        </form>
    </x-ui.section>
</div>
