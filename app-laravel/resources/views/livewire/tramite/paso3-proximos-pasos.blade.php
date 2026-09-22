<div class="rounded-lg border-[0.5px] border-hairline bg-canvas p-lg">
    <h1 class="font-display text-display-sm font-semibold text-ink mb-xs">Captura inicial completa</h1>
    <p class="font-sans text-body-sm text-body">
        Inmueble, infraestructura y mobiliario completos — el resto de la captura está pendiente.
    </p>

    @if ($nivelesPendientes->isNotEmpty())
        <div class="mt-lg pt-lg border-t-[0.5px] border-hairline">
            <p class="font-sans text-body-sm font-medium text-ink mb-xs">Esta escuela tiene otros niveles por capturar</p>
            <ul class="space-y-xxs">
                @foreach ($nivelesPendientes as $nivel)
                    <li>
                        <a href="{{ route('tramite.paso3-inmueble', ['escuelaNivel' => $nivel->id]) }}" class="font-sans text-body-sm text-primary hover:underline">
                            Continuar con {{ $nivel->nombre }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
