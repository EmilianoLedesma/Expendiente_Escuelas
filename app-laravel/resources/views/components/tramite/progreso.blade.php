<ol class="flex flex-wrap items-center gap-sm font-sans text-nav-link">
    @foreach ($pasos as $paso)
        <li
            @class([
                'flex items-center gap-xxs',
                'text-muted' => $paso->estado === 'pendiente',
                'text-ink' => $paso->estado !== 'pendiente',
            ])
            data-estado="{{ $paso->estado }}"
        >
            <span
                @class([
                    'inline-block w-2 h-2 rounded-full',
                    'bg-success' => $paso->estado === 'completado',
                    'bg-primary' => $paso->estado === 'en_progreso',
                    'bg-hairline' => $paso->estado === 'pendiente',
                ])
            ></span>
            {{ $paso->nombre }}
        </li>
    @endforeach
</ol>
