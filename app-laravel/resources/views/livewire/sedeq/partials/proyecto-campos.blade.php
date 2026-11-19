{{-- Campos del proyecto educativo para el evaluador. $filas: list<[etiqueta, valor]>. Los valores cortos (<= 120 caracteres, sin salto de línea) van primero en una rejilla; los largos, después, como bloques de lectura de ancho completo (saltos respetados; más de 1500 caracteres se pliega con «Ver más», el texto completo queda en el DOM). Dentro de cada tipo se conserva el orden original. --}}
@php
    $corto = fn ($v) => ! is_string($v) || (mb_strlen($v) <= 120 && ! str_contains($v, "\n"));
    $cortos = array_values(array_filter($filas, fn ($f) => $corto($f[1])));
    $largos = array_values(array_filter($filas, fn ($f) => ! $corto($f[1])));
@endphp
@if ($cortos !== [])
    <dl data-campos="cortos" class="grid gap-x-lg gap-y-md sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cortos as [$etiqueta, $valor])
            <div class="min-w-0">
                <dt class="text-body-sm font-semibold text-muted">{{ $etiqueta }}</dt>
                <dd class="proyecto-texto mt-xxs break-words text-body-md text-ink">{{ filled($valor) ? $valor : '—' }}</dd>
            </div>
        @endforeach
    </dl>
@endif
@if ($largos !== [])
    <dl data-campos="largos" class="divide-y divide-hairline border-y border-hairline">
        @foreach ($largos as [$etiqueta, $valor])
            @php($plegable = mb_strlen($valor) > 1500)
            <div class="py-md" @if ($plegable) x-data="{ abierto: false }" @endif>
                <dt class="text-body-sm font-semibold text-muted">{{ $etiqueta }}</dt>
                @if ($plegable)
                    <dd x-bind:class="abierto ? '' : 'max-h-64 overflow-hidden xl:[column-fill:auto]'" class="proyecto-texto relative mt-xs max-w-[85ch] xl:max-w-none xl:columns-2 xl:gap-x-xl whitespace-pre-line break-words text-body-md leading-relaxed text-ink">{{ $valor }}<span x-show="! abierto" class="pointer-events-none absolute inset-x-0 bottom-0 block h-16 bg-gradient-to-t from-canvas to-transparent" aria-hidden="true"></span></dd>
                    <button type="button" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto" x-text="abierto ? 'Ver menos' : 'Ver más'"
                            class="mt-xs inline-flex min-h-11 items-center font-semibold text-primary underline">Ver más</button>
                @else
                    <dd class="proyecto-texto mt-xs max-w-[85ch] xl:max-w-none xl:columns-2 xl:gap-x-xl whitespace-pre-line break-words text-body-md leading-relaxed text-ink">{{ $valor }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
@endif
