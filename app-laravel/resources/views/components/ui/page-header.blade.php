@props(['title', 'backHref' => null, 'backLabel' => 'Resumen del trámite', 'eyebrow' => null])

<header class="mb-lg">
    @if ($backHref)
        <a href="{{ $backHref }}" class="inline-flex min-h-11 items-center gap-xxs text-body-sm font-semibold text-primary underline underline-offset-4 hover:no-underline">
            <x-ui.icon nombre="arrow-left" class="h-4 w-4" />
            {{ $backLabel }}
        </a>
    @endif

    @if ($eyebrow)
        <p class="mt-xs text-section-eyebrow font-semibold uppercase text-muted">{{ $eyebrow }}</p>
    @endif

    <h1 class="mt-xxs font-display text-display-md text-ink">{{ $title }}</h1>

    @isset($intro)
        <p class="mt-xs text-body-md text-body">{{ $intro }}</p>
    @endisset
</header>
