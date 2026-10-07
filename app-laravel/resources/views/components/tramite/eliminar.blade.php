{{-- Borrado definitivo en dos pasos, sin JS: <details> abre la confirmación y solo su botón envía el DELETE. --}}
@props(['tramite'])

<details {{ $attributes->merge(['class' => 'group']) }}>
    <summary class="inline-flex min-h-11 cursor-pointer list-none items-center justify-center gap-xs rounded-md border border-error-ink bg-canvas px-lg text-body-md font-semibold text-error-ink hover:bg-error-soft [&::-webkit-details-marker]:hidden">
        {{-- Abierto, el mismo summary cierra la confirmación: su texto cambia a "Cancelar". --}}
        <span class="inline-flex items-center gap-xs group-open:hidden">
            <x-ui.icon nombre="trash" class="h-5 w-5" />
            Eliminar trámite<span class="sr-only">: Nº {{ $tramite->numero() }}</span>
        </span>
        <span class="hidden items-center gap-xs group-open:inline-flex">
            <x-ui.icon nombre="x-mark" class="h-5 w-5" />
            Cancelar<span class="sr-only"> la eliminación del trámite Nº {{ $tramite->numero() }}</span>
        </span>
    </summary>
    <div class="mt-sm w-72 max-w-full whitespace-normal rounded-md border border-error-ink bg-error-soft p-md text-left text-body-sm text-error-ink">
        <p class="font-semibold">Esta acción no se puede deshacer.</p>
        <p class="mt-xxs">Se borrará el trámite, sus datos y archivos. El domicilio del plantel no se elimina.</p>
        <form method="POST" action="{{ route('tramite.eliminar', ['escuela' => $tramite->escuelaId]) }}" class="mt-md">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center whitespace-nowrap rounded-md bg-error-ink px-md text-body-md font-semibold text-on-primary">
                Sí, eliminar trámite
            </button>
        </form>
    </div>
</details>
