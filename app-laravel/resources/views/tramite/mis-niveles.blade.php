<x-tramite-layout title="Mis niveles asignados">
    <x-ui.page-header title="Mis niveles asignados">
        <x-slot:intro>Niveles de incorporación que el solicitante te asignó. Solo puedes capturar la información de estos niveles.</x-slot:intro>
    </x-ui.page-header>

    <ul class="max-w-3xl divide-y divide-hairline rounded-lg border border-hairline">
        @foreach ($niveles as $nivel)
            <li class="flex flex-wrap items-center justify-between gap-sm p-md">
                <div>
                    <p class="font-semibold text-ink">{{ $nivel['nivel'] }}</p>
                    <p class="break-words text-caption text-muted">{{ $nivel['etiqueta'] }}</p>
                </div>
                <x-ui.button-primary :href="route('tramite.resumen', ['escuela' => $nivel['escuelaId']])">
                    Abrir<span class="sr-only"> {{ $nivel['nivel'] }}</span>
                </x-ui.button-primary>
            </li>
        @endforeach
    </ul>
</x-tramite-layout>
