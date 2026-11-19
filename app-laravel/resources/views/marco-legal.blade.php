{{-- Marco legal del trámite: página pública (sin sesión). Contenido en config/marco_legal.php. --}}
<x-tramite-layout title="Marco legal del trámite">
    <div class="max-w-3xl">
        <x-ui.page-header eyebrow="Información pública" title="Marco legal del trámite">
            <x-slot:intro>Leyes, acuerdos y procedimientos que rigen la incorporación de escuelas particulares. Cualquier persona puede consultarlos.</x-slot:intro>
        </x-ui.page-header>

        <x-ui.alert tipo="info" class="mb-lg">
            Esta página resume los textos para orientarte. No sustituye el texto oficial: ante cualquier duda consulta la fuente o a la Dirección de Educación.
            Última verificación: {{ \Illuminate\Support\Carbon::parse(config('marco_legal.verificado_el'))->format('d/m/Y') }}.
        </x-ui.alert>

        <x-ui.section title="Etapas del procedimiento">
            <ol class="list-decimal space-y-xs pl-lg text-body-md">
                @foreach (config('marco_legal.procedimiento') as $etapa)
                    <li>{{ $etapa }}</li>
                @endforeach
            </ol>
        </x-ui.section>

        @foreach (config('marco_legal.grupos') as $grupo)
            <x-ui.section :title="$grupo['titulo']">
                <div class="space-y-lg">
                    @foreach ($grupo['entradas'] as $entrada)
                        <article class="rounded-lg border border-hairline p-md">
                            <p class="text-caption font-semibold uppercase text-muted">{{ $entrada['tipo'] }}</p>
                            <h3 class="mt-xxs text-title-sm font-semibold text-ink">{{ $entrada['titulo'] }}</h3>
                            <p class="mt-xs text-body-md">{{ $entrada['rige'] }}</p>
                            <ul class="mt-sm list-disc space-y-xxs pl-lg text-body-sm">
                                @foreach ($entrada['articulos'] as $articulo)
                                    <li>{{ $articulo }}</li>
                                @endforeach
                            </ul>
                            @if ($entrada['nota'])
                                <p class="mt-sm text-body-sm text-muted">{{ $entrada['nota'] }}</p>
                            @endif
                            @if ($entrada['url'])
                                <p class="mt-sm">
                                    <a href="{{ $entrada['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center font-semibold text-primary">
                                        Abrir el texto<span class="sr-only"> de {{ $entrada['titulo'] }} (abre en una pestaña nueva)</span>
                                    </a>
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </x-ui.section>
        @endforeach
    </div>
</x-tramite-layout>
