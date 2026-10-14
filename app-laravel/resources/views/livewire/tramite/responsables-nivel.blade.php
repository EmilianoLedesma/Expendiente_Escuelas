<div class="mx-auto w-full max-w-3xl py-lg">
    <x-ui.page-header :back-href="route('tramite.index')" back-label="Mis trámites" title="Responsables por nivel">
        <x-slot:intro>Opcional. Asigna a otra persona para que capture la información de un nivel de tus trámites: solo verá ese nivel. Si no la necesitas, sigue tu trámite como siempre.</x-slot:intro>
    </x-ui.page-header>

    <x-ui.section title="Asignar un responsable">
        @php($asignables = array_filter($filas, fn ($f) => $f['asignable']))
        @if ($asignables === [])
            <p class="text-body-md text-muted">No tienes niveles en captura para asignar. Selecciona los niveles de tu trámite en el Paso 2 y vuelve aquí.</p>
        @else
            <form novalidate>
                <x-ui.error-summary />
                <div class="grid grid-cols-1 gap-md sm:grid-cols-2 sm:items-end">
                    <x-ui.field id="escuelaNivelId" label="Nivel" class="sm:col-span-2">
                        <x-ui.select wire:model.blur="escuelaNivelId">
                            <option value="">Selecciona un nivel…</option>
                            @foreach ($asignables as $fila)
                                <option value="{{ $fila['escuelaNivelId'] }}">{{ $fila['etiqueta'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field id="nombre" label="Nombre completo" hint="Nombre y apellidos de la persona.">
                        <x-ui.input maxlength="255" wire:model.blur="nombre" autocomplete="off" />
                    </x-ui.field>
                    <x-ui.field id="correo" label="Correo electrónico" hint="Le llegará un enlace para definir su contraseña.">
                        <x-ui.input type="email" maxlength="255" wire:model.blur="correo" autocomplete="off" />
                    </x-ui.field>
                </div>
                <x-ui.stateful-button type="submit" action="invitar" label="Enviar invitación" success-label="Invitación enviada" error-label="No se pudo enviar. Reintentar" :reset-after="4000" class="mt-md" />
            </form>
        @endif
    </x-ui.section>

    <x-ui.section title="Responsables asignados">
        @error('revocar')
            <x-ui.alert tipo="error" class="mb-md">{{ $message }}</x-ui.alert>
        @enderror
        @forelse ($filas as $fila)
            <div wire:key="nivel-{{ $fila['escuelaNivelId'] }}" class="border-b border-hairline py-md first:pt-0 last:border-0">
                <h3 class="break-words text-body-md font-semibold text-ink">{{ $fila['etiqueta'] }}</h3>
                @forelse ($fila['responsables'] as $responsable)
                    <div wire:key="acceso-{{ $responsable['id'] }}" class="mt-sm flex flex-wrap items-center justify-between gap-sm">
                        <p class="break-words text-body-sm"><span class="font-semibold text-ink">{{ $responsable['nombre'] }}</span> · {{ $responsable['correo'] }}</p>
                        <button type="button" wire:click="revocar({{ $responsable['id'] }})"
                                wire:confirm="¿Revocar el acceso de {{ $responsable['nombre'] }} a este nivel? Se elimina su cuenta si no tiene otros accesos. Lo que ya capturó se conserva."
                                class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-md border border-error-ink bg-canvas px-md text-body-sm font-semibold text-error-ink hover:bg-error-soft">
                            Revocar acceso<span class="sr-only"> de {{ $responsable['nombre'] }}</span>
                        </button>
                    </div>
                @empty
                    <p class="mt-xs text-caption text-muted">Sin responsable asignado.</p>
                @endforelse
            </div>
        @empty
            <p class="text-body-md text-muted">Aún no tienes niveles. Inicia un trámite y selecciona sus niveles en el Paso 2.</p>
        @endforelse
    </x-ui.section>

</div>
