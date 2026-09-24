@props(['title'])

<section {{ $attributes->merge(['class' => 'mt-xl border-t border-hairline pt-lg first:mt-0 first:border-t-0 first:pt-0']) }}>
    <h2 class="mb-md text-section-eyebrow font-semibold uppercase text-muted">{{ $title }}</h2>
    <div class="space-y-md">{{ $slot }}</div>
</section>
