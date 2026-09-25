{{-- Color de nivel (tokens nivel-* de tailwind.config.js). Posgrado no tiene token: queda en gris (pendiente del dueño de diseño). --}}
@props(['clave', 'como' => 'punto'])

@php
    [$borde, $fondo] = match ($clave) {
        'inicial' => ['border-nivel-inicial-esc', 'bg-nivel-inicial-esc'],
        'preescolar' => ['border-nivel-preescolar', 'bg-nivel-preescolar'],
        'primaria' => ['border-nivel-primaria', 'bg-nivel-primaria'],
        'secundaria' => ['border-nivel-secundaria', 'bg-nivel-secundaria'],
        'media_superior' => ['border-nivel-media-superior', 'bg-nivel-media-superior'],
        'superior' => ['border-nivel-superior', 'bg-nivel-superior'],
        default => ['border-hairline', 'bg-hairline'],
    };
@endphp

@if ($como === 'franja')
    <div {{ $attributes->merge(['class' => "border-l-4 pl-md $borde"]) }}>{{ $slot }}</div>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-xs rounded-pill border border-hairline bg-canvas px-sm py-xxs text-body-sm text-ink']) }}>
        <span class="h-2.5 w-2.5 rounded-full {{ $fondo }}" aria-hidden="true"></span>
        {{ $slot }}
    </span>
@endif
