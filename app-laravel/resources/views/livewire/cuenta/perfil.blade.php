<div>
    <x-ui.page-header title="Mi perfil">
        <x-slot:intro>Cambia tu nombre y tu contraseña. El correo identifica tu cuenta y no se modifica desde aquí.</x-slot:intro>
    </x-ui.page-header>

    <x-ui.section title="Datos personales">
        <form novalidate wire:submit="guardarNombre" class="max-w-xl space-y-md">
            <x-ui.field id="nombre" label="Nombre completo">
                <x-ui.input maxlength="255" wire:model="nombre" autocomplete="name" />
            </x-ui.field>
            <div>
                <p class="block text-body-md font-semibold text-ink">Correo electrónico</p>
                <p class="mt-xs break-words text-body-md text-muted">{{ $correo }}</p>
            </div>
            <x-ui.button-primary type="submit">Guardar nombre</x-ui.button-primary>
        </form>
    </x-ui.section>

    <x-ui.section title="Cambiar contraseña">
        <form novalidate wire:submit="cambiarContrasena" class="max-w-xl space-y-md">
            <x-ui.field id="contrasenaActual" label="Contraseña actual">
                <x-ui.input type="password" wire:model="contrasenaActual" autocomplete="current-password" />
            </x-ui.field>
            <x-ui.field id="contrasenaNueva" label="Nueva contraseña" hint="Mínimo 8 caracteres.">
                <x-ui.input type="password" wire:model="contrasenaNueva" autocomplete="new-password" />
            </x-ui.field>
            <x-ui.field id="contrasenaNueva_confirmation" label="Confirmar nueva contraseña">
                <x-ui.input type="password" wire:model="contrasenaNueva_confirmation" autocomplete="new-password" />
            </x-ui.field>
            <x-ui.button-primary type="submit">Cambiar contraseña</x-ui.button-primary>
        </form>
    </x-ui.section>

    @if ($puedeEliminar)
        <x-ui.section title="Eliminar cuenta">
            <p class="max-w-xl text-body-md">Se borra tu cuenta y, si eres quien inició los trámites, también los trámites y sus archivos. Solo es posible mientras ningún trámite se haya enviado a SEDEQ. Esta acción no se puede deshacer.</p>
            @error('cuenta')
                <x-ui.alert tipo="error">{{ $message }}</x-ui.alert>
            @enderror
            <p id="eliminar-cuenta-ayuda" class="text-body-sm text-muted">Mantén presionado el botón rojo (o Enter / Espacio) casi 2 segundos. Si lo sueltas antes, no pasa nada.</p>
            <x-ui.hold-button accion="$wire.eliminarCuenta()" aria-describedby="eliminar-cuenta-ayuda">
                <x-ui.icon nombre="trash" class="h-5 w-5" />
                Mantén presionado para eliminar
            </x-ui.hold-button>
        </x-ui.section>
    @endif
</div>
