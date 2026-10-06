{{-- Pie institucional en tres franjas (estilo portal queretaro.gob.mx). Datos: config('sedeq'). --}}
@php($contacto = config('sedeq.contacto'))
<footer class="shell-oscuro mt-xxl overflow-hidden text-white">
    <h2 class="sr-only">Información institucional</h2>

    {{-- Franja heráldica. Onda dibujada aquí (no es el bg-footer.png del portal, que vive en queretaro.gob.mx):
         listón pálido detrás, azul delante; baja a la izquierda, cresta ~40 % y cae hacia la derecha.
         El listón casi pega con el azul en la subida y se ensancha en la bajada derecha. --}}
    <svg data-onda xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 160" preserveAspectRatio="none" aria-hidden="true" focusable="false"
         class="-mb-px block h-16 w-full sm:h-24 lg:h-32">
        <path class="fill-brand-blue-pale" d="M0 134C200 86 380 36 580 32c300-4 560 70 860 88v40H0Z" />
        <path class="fill-brand-blue" d="M0 140C220 90 400 40 600 38c300-2 550 102 840 118v4H0Z" />
    </svg>
    <div class="flex justify-center bg-brand-blue px-md pb-md">
        <img src="{{ asset('img/heraldicas.png') }}" alt="Gobierno de Querétaro" width="824" height="421"
             class="relative h-auto w-[150px] sm:-mt-8 sm:w-[180px] lg:-mt-14">
    </div>

    {{-- Franja de contacto --}}
    <div class="bg-brand-blue">
        <div class="mx-auto grid max-w-7xl gap-xxl px-md pb-xxl pt-xxl text-center text-body-md leading-relaxed sm:grid-cols-2 sm:px-xl lg:grid-cols-4 lg:pb-16 lg:pt-section">
            <div>
                <x-ui.icon nombre="pie-ubicacion" class="mx-auto mb-md h-16 w-16" />
                <h3 class="mb-xs text-title-sm font-bold uppercase tracking-wide">Dirección</h3>
                <p>{{ config('sedeq.institucion') }}<br>{{ $contacto['direccion'] }}</p>
            </div>
            <div>
                <x-ui.icon nombre="pie-telefono" class="mx-auto mb-md h-16 w-16" />
                <h3 class="mb-xs text-title-sm font-bold uppercase tracking-wide">Teléfono</h3>
                <p>{!! implode('<br>', array_map('e', $contacto['telefonos'])) !!}</p>
            </div>
            @foreach (['Atención ciudadana', 'Web master'] as $titulo)
                <div>
                    <x-ui.icon nombre="pie-correo" class="mx-auto mb-md h-16 w-16" />
                    <h3 class="mb-xs text-title-sm font-bold uppercase tracking-wide">{{ $titulo }}</h3>
                    <p>{{ $contacto['atencion'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Franja legal. El aviso lleva subrayado permanente discreto (60 % blanco; pleno al pasar el cursor) para que se
         distinga del texto vecino sin depender del color (1.4.1), más el anillo blanco de .shell-oscuro al enfocar. --}}
    <div class="bg-surface-dark">
        <div class="mx-auto flex max-w-7xl flex-col gap-lg px-md py-xxl text-title-md font-normal md:flex-row md:items-center md:justify-between sm:px-xl lg:py-16">
            <div>
                <p>
                    <a href="{{ config('sedeq.aviso_privacidad') }}" target="_blank" rel="noopener noreferrer"
                       class="-mx-xs inline-flex min-h-11 items-center px-xs text-white underline decoration-white/60 underline-offset-4 hover:decoration-white">
                        Aviso de privacidad<span class="sr-only"> (abre en una pestaña nueva)</span>
                    </a>
                </p>
                <p>PODER EJECUTIVO DEL ESTADO DE QUERÉTARO Copyright © {{ date('Y') }} Derechos Reservados.</p>
                <p class="mt-xs text-body-sm text-white/80">Sistema de Incorporación · versión MVP</p>
            </div>

            <x-shell.redes class="justify-center gap-xs md:justify-end" icono="h-6 w-6" />
        </div>
    </div>
</footer>
