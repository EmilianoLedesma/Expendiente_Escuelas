{{-- Borrado definitivo en dos pasos: el botón abre un <dialog> modal (capa superior, no depende del ancho de la fila) y solo su botón envía el DELETE. --}}
@props(['tramite'])

@php($titulo = 'eliminar-tramite-'.$tramite->escuelaId)

<div {{ $attributes }}>
    <button type="button" onclick="document.getElementById('{{ $titulo }}').showModal()" class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-xs rounded-md border border-error-ink bg-canvas px-lg text-body-md font-semibold text-error-ink hover:bg-error-soft">
        <x-ui.icon nombre="trash" class="h-5 w-5" />
        Eliminar trámite<span class="sr-only">: Nº {{ $tramite->numero() }}</span>
    </button>

    <dialog id="{{ $titulo }}" aria-labelledby="{{ $titulo }}-titulo" onclick="if (event.target === this) this.close()" class="modal rounded-lg border border-error-ink bg-canvas p-lg text-left text-body-md text-ink">
        <h2 id="{{ $titulo }}-titulo" class="text-title-md font-semibold text-error-ink">Esta acción no se puede deshacer.</h2>
        <p class="mt-sm">Se borrará el trámite, sus datos y archivos. El domicilio del plantel no se elimina.</p>
        <form method="POST" action="{{ route('tramite.eliminar', ['escuela' => $tramite->escuelaId]) }}" class="mt-lg flex flex-wrap justify-end gap-sm">
            @csrf
            @method('DELETE')
            <button type="button" onclick="this.closest('dialog').close()" class="inline-flex min-h-11 items-center justify-center rounded-md border border-control bg-canvas px-lg text-body-md font-semibold text-ink transition duration-150 hover:border-ink hover:bg-surface-card active:bg-surface-soft">
                Cancelar<span class="sr-only"> la eliminación del trámite Nº {{ $tramite->numero() }}</span>
            </button>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-error-ink px-lg text-body-md font-semibold text-on-primary transition duration-150 hover:brightness-90 hover:shadow-md active:brightness-75">
                Sí, eliminar trámite
            </button>
        </form>
    </dialog>
</div>
