@props(['title'])

<section {{ $attributes->merge(['class' => 'mt-lg rounded-lg border border-hairline bg-canvas first:mt-0']) }}>
    <h2 class="rounded-t-lg border-b border-hairline bg-surface-soft px-md py-sm text-title-sm font-semibold text-ink sm:px-lg">{{ $title }}</h2>
    <div class="space-y-md p-md sm:p-lg">{{ $slot }}</div>
</section>
