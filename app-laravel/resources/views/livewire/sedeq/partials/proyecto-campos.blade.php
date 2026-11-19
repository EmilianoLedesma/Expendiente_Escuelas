{{-- Campos del proyecto educativo para el evaluador: etiqueta arriba, valor en bloque de lectura (medida cómoda, saltos de línea respetados). $filas: list<[etiqueta, valor]>. --}}
<dl class="divide-y divide-hairline border-y border-hairline">
    @foreach ($filas as [$etiqueta, $valor])
        <div class="py-md">
            <dt class="text-body-sm font-semibold text-muted">{{ $etiqueta }}</dt>
            <dd class="proyecto-texto mt-xs max-w-prose whitespace-pre-line break-words text-body-md leading-relaxed text-ink">{{ filled($valor) ? $valor : '—' }}</dd>
        </div>
    @endforeach
</dl>
