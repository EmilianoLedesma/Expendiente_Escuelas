<x-tramite-layout title="Mis trámites">
    <x-ui.page-header title="Mis trámites" />

    @if ($tramites === [])
        <div class="space-y-md text-body-md text-body">
            <p>Este sistema te guía para capturar la solicitud de incorporación de una escuela particular: los datos del plantel, el responsable legal, los documentos y la información de cada nivel educativo.</p>
            <p>Ten a la mano, en PDF, la identificación oficial del responsable legal y los documentos del inmueble: escritura o contrato, dictamen de uso de suelo y constancia de seguridad estructural.</p>
            <p>Tu avance se guarda al terminar cada sección: puedes salir y continuar después desde esta página.</p>
        </div>
    @else
        <ul class="divide-y divide-hairline border-y border-hairline">
            @foreach ($tramites as $tramite)
                <li class="flex flex-col gap-sm py-md sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="break-words text-body-md font-semibold text-ink">{{ $tramite->domicilio }}</h2>
                        <ul class="mt-xs flex flex-wrap gap-xs" aria-label="Niveles educativos">
                            @forelse ($tramite->niveles as $nivel)
                                <li><x-tramite.nivel :clave="$nivel->clave">{{ $nivel->nombre }}</x-tramite.nivel></li>
                            @empty
                                <li class="text-body-sm text-muted">Sin niveles seleccionados</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-md">
                        <x-ui.status-tag
                            :estado="$tramite->completo ? 'completado' : 'en_curso'"
                            :texto="$tramite->completo ? 'Captura inicial completa' : 'En captura'"
                        />
                        <a href="{{ route('tramite.resumen', ['escuela' => $tramite->escuelaId]) }}" class="inline-flex min-h-11 items-center font-semibold text-primary underline underline-offset-4 hover:no-underline">
                            Ver trámite<span class="sr-only">: {{ $tramite->domicilio }}</span>
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-xl">
        <x-ui.button-primary :href="route('tramite.preregistro')">Iniciar nuevo trámite</x-ui.button-primary>
    </div>
</x-tramite-layout>
