{{-- Avisos tras guardar/enviar/borrar. Se monta una vez por layout. Llegan por el evento `toast` (Livewire:
     NotificaToast) o por flash de sesión (`toast`, o `status` heredado) en la carga siguiente a una redirección.
     Éxito e info se cierran solos a los 5 s; los errores esperan a que se cierren. Alpine llega con Livewire. --}}
@php
    $flash = session('toast');
    $iniciales = match (true) {
        is_array($flash) && isset($flash['titulo']) => [$flash],
        is_array($flash) => array_values($flash),
        // Breeze deja este código técnico en `status` (verificación de correo): se traduce aquí, una sola vez.
        session('status') === 'verification-link-sent' => [['tipo' => 'success', 'titulo' => 'Enlace enviado', 'descripcion' => 'Enviamos un nuevo enlace de verificación al correo que registraste.']],
        is_string(session('status')) && session('status') !== '' => [['tipo' => 'success', 'titulo' => session('status')]],
        default => [],
    };
@endphp

<div data-toast-region role="region" aria-label="Notificaciones" aria-live="polite"
     x-data="{
         items: [],
         n: 0,
         add(t) {
             const id = ++this.n
             const tipo = t.tipo || 'success'
             this.items.push({ id, tipo, titulo: t.titulo || '', descripcion: t.descripcion || null })
             if (tipo !== 'error') setTimeout(() => this.quitar(id), 5000)
         },
         quitar(id) { this.items = this.items.filter(i => i.id !== id) },
     }"
     x-init="@js($iniciales).forEach(t => add(t))"
     x-on:toast.window="add($event.detail)"
     class="pointer-events-none fixed bottom-md right-md z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-xs">
    <template x-for="item in items" :key="item.id">
        <div x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
             x-transition:enter-start="translate-y-2 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-out duration-150 motion-reduce:transition-none"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-2 opacity-0"
             :role="item.tipo === 'error' ? 'alert' : 'status'"
             :class="{
                 'border-l-success-ink': item.tipo === 'success',
                 'border-l-error-ink': item.tipo === 'error',
                 'border-l-info-ink': item.tipo === 'info',
             }"
             class="pointer-events-auto relative flex gap-sm rounded-lg border border-l-4 border-hairline bg-canvas p-md pr-xl shadow-lg">
            <span :class="{ 'text-success-ink': item.tipo === 'success', 'text-error-ink': item.tipo === 'error', 'text-info-ink': item.tipo === 'info' }" class="shrink-0">
                <x-ui.icon nombre="check-circle" class="h-6 w-6" x-show="item.tipo === 'success'" />
                <x-ui.icon nombre="exclamation-circle" class="h-6 w-6" x-show="item.tipo === 'error'" />
                <x-ui.icon nombre="information-circle" class="h-6 w-6" x-show="item.tipo === 'info'" />
            </span>
            <div class="min-w-0">
                <p class="break-words text-body-md font-semibold text-ink" x-text="item.titulo"></p>
                <p class="mt-xxs break-words text-body-sm text-muted" x-show="item.descripcion" x-text="item.descripcion"></p>
            </div>
            <button type="button" x-on:click="quitar(item.id)"
                    class="absolute right-xs top-xs inline-flex size-8 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-soft hover:text-ink">
                <x-ui.icon nombre="x-mark" class="h-4 w-4" />
                <span class="sr-only">Cerrar aviso</span>
            </button>
        </div>
    </template>
</div>
