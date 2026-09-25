{{-- $filas: etiqueta => valor; null o '' se muestra como —. --}}
@props(['filas'])

<dl {{ $attributes->merge(['class' => 'divide-y divide-hairline border-y border-hairline']) }}>
    @foreach ($filas as $etiqueta => $valor)
        <div class="py-sm sm:grid sm:grid-cols-3 sm:gap-md">
            <dt class="text-body-sm font-semibold text-ink">{{ $etiqueta }}</dt>
            <dd class="mt-xxs break-words text-body-md text-body sm:col-span-2 sm:mt-0">{{ filled($valor) ? $valor : '—' }}</dd>
        </div>
    @endforeach
</dl>
