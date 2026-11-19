{{-- Visita de inspección de un nivel (solicitante). Los datos vienen de ConsultarVisita; la respuesta llama a ResponderObservaciones. --}}
@php
    $actual = $datos['visita'];
    $archivoClases = 'block w-full text-body-md text-body file:mr-sm file:min-h-11 file:cursor-pointer file:rounded-md file:border file:border-solid file:border-primary file:bg-canvas file:px-md file:font-semibold file:text-primary';
    // Un archivo que falla la subida temporal (tamaño, tipo) deja el error en archivos.N, que x-ui.field no pinta.
    $errorSubida = ! $errors->has('archivos') && $errors->has('archivos.*');
    $anteriores = array_reverse(array_filter($datos['visitas'], fn ($v) => $actual !== null && $v['id'] !== $actual['id']));
@endphp
<div class="max-w-3xl">
    <x-ui.page-header title="Visita de inspección" :back-href="route('tramite.index')" back-label="Mis trámites">
        <x-slot:intro><span class="break-words">{{ $datos['escuela'] }} · {{ $datos['nivel'] }}</span></x-slot:intro>
    </x-ui.page-header>

    <x-tramite.aviso-sin-acuerdo class="mb-lg" />

    @if ($enviada)
        <x-ui.alert tipo="success" class="mb-lg" role="status">Tu respuesta se envió; la autoridad educativa la revisará.</x-ui.alert>
    @endif
    @error('general')
        <x-ui.alert tipo="error" class="mb-lg">{{ $message }}</x-ui.alert>
    @enderror

    <div class="space-y-lg">
        <x-ui.section title="Estado">
            <p class="text-body-md font-semibold text-ink">{{ $estadoTexto }}</p>
            @if ($actual && $actual['estado'] === 'realizada' && $actual['resultado'] === 'con_observaciones' && $actual['subsanarHasta'])
                <p class="mt-xs text-body-md text-body">Plazo aprox. para responder: {{ $actual['subsanarHasta']->format('d/m/Y') }} (aprox.; 5 días hábiles, solo informativo)</p>
            @endif
            @unless ($esDueno)
                <p class="mt-xs text-body-sm text-muted">Solo el titular del trámite puede responder a las observaciones y descargar los archivos.</p>
            @endunless
        </x-ui.section>

        @if ($actual)
            <x-ui.section :title="'Visita '.$actual['numero']">
                @include('livewire.tramite.partials.visita-detalle', ['v' => $actual, 'nivelId' => $escuelaNivelId, 'descargas' => $esDueno, 'domicilio' => $datos['domicilioPlantel']])
            </x-ui.section>
        @endif

        @if ($puedeResponder)
            <x-ui.section title="Responder las observaciones">
                <form novalidate wire:submit="responder" class="space-y-md">
                    <x-ui.field id="nota" label="Tu respuesta" hint="Cuenta qué corregiste. Hasta 2000 caracteres.">
                        <x-ui.textarea wire:model="nota" maxlength="2000" />
                    </x-ui.field>

                    <x-ui.field id="archivos" label="Archivos PDF (opcional)" hint="Hasta 5 archivos PDF, de 10 MB cada uno.">
                        <input type="file" id="archivos" wire:model="archivos" multiple accept="application/pdf"
                            aria-describedby="archivos-hint @if ($errors->has('archivos') || $errorSubida) archivos-error @endif" @if ($errors->has('archivos') || $errorSubida) aria-invalid="true" @endif
                            class="{{ $archivoClases }}">
                        <p wire:loading wire:target="archivos" class="mt-xxs text-body-sm text-muted">Subiendo…</p>
                        @if ($errorSubida)
                            <p id="archivos-error" role="alert" class="mt-xxs text-body-sm font-semibold text-error-ink">
                                <span class="sr-only">Error:</span> Cada archivo debe ser un PDF de máximo 10 MB.
                            </p>
                        @endif
                        @if ($archivos !== [])
                            <ul class="mt-xs space-y-xs">
                                @foreach ($archivos as $i => $archivo)
                                    <li class="flex items-center gap-xs" wire:key="archivo-{{ $i }}">
                                        <span class="min-w-0 flex-1 break-all text-body-sm text-body">{{ $archivo->getClientOriginalName() }}</span>
                                        <button type="button" wire:click="quitarArchivo({{ $i }})" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md border border-control text-body-sm font-semibold text-ink">
                                            <span aria-hidden="true">&times;</span><span class="sr-only">Quitar {{ $archivo->getClientOriginalName() }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-ui.field>

                    <x-ui.button-primary wire:loading.attr="disabled" wire:target="responder">Enviar respuesta</x-ui.button-primary>
                </form>
            </x-ui.section>
        @endif

        @if ($anteriores !== [])
            <x-ui.section title="Visitas anteriores">
                @foreach ($anteriores as $v)
                    <div class="space-y-md" wire:key="anterior-{{ $v['id'] }}">
                        <h3 class="text-body-md font-semibold text-ink">Visita {{ $v['numero'] }}</h3>
                        @include('livewire.tramite.partials.visita-detalle', ['v' => $v, 'nivelId' => $escuelaNivelId, 'descargas' => $esDueno, 'domicilio' => $datos['domicilioPlantel']])
                    </div>
                @endforeach
            </x-ui.section>
        @endif
    </div>
</div>
