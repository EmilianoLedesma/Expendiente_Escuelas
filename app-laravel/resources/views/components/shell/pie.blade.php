{{-- Pie institucional en tres franjas (estilo tableros_municipales). Datos: config('sedeq'). --}}
@php($contacto = config('sedeq.contacto'))
<footer class="shell-oscuro mt-xxl text-white">
    <h2 class="sr-only">Información institucional</h2>

    {{-- Franja heráldica. El fondo del original (bg-footer.png) se sirve desde queretaro.gob.mx: se sustituye por un degradado propio. --}}
    <div class="flex justify-center bg-gradient-to-b from-surface-dark to-brand-blue px-md py-xl">
        <img src="{{ asset('img/heraldicas.png') }}" alt="Gobierno de Querétaro" width="824" height="421" class="h-auto w-[150px] sm:w-[200px]">
    </div>

    {{-- Franja de contacto --}}
    <div class="bg-brand-blue">
        <div class="mx-auto grid max-w-7xl gap-lg px-md py-xl text-center text-body-sm sm:grid-cols-2 sm:px-xl lg:grid-cols-4">
            <div>
                <x-ui.icon nombre="map-pin" class="mx-auto mb-xs h-8 w-8" />
                <h3 class="mb-xs font-bold uppercase tracking-wide">{{ config('sedeq.institucion') }}</h3>
                <p>{{ $contacto['direccion'] }}</p>
            </div>
            <div>
                <x-ui.icon nombre="phone" class="mx-auto mb-xs h-8 w-8" />
                <h3 class="mb-xs font-bold uppercase tracking-wide">Teléfono</h3>
                <p>{!! implode('<br>', array_map('e', $contacto['telefonos'])) !!}</p>
            </div>
            @foreach (['Atención ciudadana', 'Web master'] as $titulo)
                <div>
                    <x-ui.icon nombre="envelope" class="mx-auto mb-xs h-8 w-8" />
                    <h3 class="mb-xs font-bold uppercase tracking-wide">{{ $titulo }}</h3>
                    <p>{{ $contacto['atencion'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Franja legal --}}
    <div class="bg-surface-dark">
        <div class="mx-auto flex max-w-7xl flex-col gap-md px-md py-xl text-body-sm md:flex-row md:items-start md:justify-between sm:px-xl">
            <div class="space-y-xxs">
                <p>
                    <a href="{{ config('sedeq.aviso_privacidad') }}" target="_blank" rel="noopener noreferrer"
                       class="-mx-xs inline-flex min-h-11 items-center px-xs font-semibold text-white underline underline-offset-4 hover:no-underline">
                        Aviso de privacidad<span class="sr-only"> (abre en una pestaña nueva)</span>
                    </a>
                </p>
                <p class="text-body-md font-semibold">PODER EJECUTIVO DEL ESTADO DE QUERÉTARO Copyright © {{ date('Y') }} Derechos Reservados.</p>
                <p>Sistema de Incorporación · versión MVP</p>
            </div>

            <x-shell.redes class="justify-center md:justify-end" />
        </div>
    </div>
</footer>
