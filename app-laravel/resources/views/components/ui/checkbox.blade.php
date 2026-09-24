@props(['id', 'descripcion' => null])

<label for="{{ $id }}" class="flex min-h-11 cursor-pointer items-start gap-sm py-xs">
    <input type="checkbox" id="{{ $id }}" {{ $attributes->merge(['class' => 'mt-0.5 h-5 w-5 shrink-0 rounded-xs border-control text-primary']) }}>
    <span class="text-body-md text-ink">
        {{ $slot }}
        @if ($descripcion)
            <span class="block text-body-sm text-muted">{{ $descripcion }}</span>
        @endif
    </span>
</label>
