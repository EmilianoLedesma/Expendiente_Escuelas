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
         // Descartar arrastrando: más de 80 px sale por ese lado (translate-x); menos, regresa a su lugar.
         empezar(e, item) { item.x0 = e.clientX; item.arrastrando = true; e.currentTarget.setPointerCapture(e.pointerId) },
         mover(e, item) { if (item.arrastrando) item.dx = e.clientX - item.x0 },
         soltar(item) {
             if (! item.arrastrando) return
             item.arrastrando = false
             if (Math.abs(item.dx || 0) < 80) { item.dx = 0; return }
             item.dx = item.dx > 0 ? 400 : -400
             item.saliendo = true
             setTimeout(() => this.quitar(item.id), 150)
         },
     }"
     x-init="@js($iniciales).forEach(t => add(t))"
     x-on:toast.window="add($event.detail)"
     class="pointer-events-none fixed bottom-md right-md z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-xs">
    {{-- Misma tarjeta y animación que el Toast de referencia: entrada/salida translate-y-2 + opacidad en 150 ms ease-out;
         arrastrar a los lados la descarta y sale por ese lado. El tipo solo se nota en el rol ARIA y en el color del título de error. --}}
    <template x-for="item in items" :key="item.id">
        <div x-transition:enter="transition-all duration-150 ease-out motion-reduce:transition-none"
             x-transition:enter-start="translate-y-2 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition-all duration-150 ease-out motion-reduce:transition-none"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-2 opacity-0"
             :role="item.tipo === 'error' ? 'alert' : 'status'"
             :style="{ transform: item.dx ? `translateX(${item.dx}px)` : null, opacity: item.saliendo ? 0 : null, transition: item.arrastrando ? 'none' : null }"
             x-on:pointerdown="empezar($event, item)"
             x-on:pointermove="mover($event, item)"
             x-on:pointerup="soltar(item)"
             x-on:pointercancel="soltar(item)"
             class="pointer-events-auto relative flex touch-pan-y flex-col gap-xxs rounded-lg border border-hairline bg-canvas p-sm shadow-md transition-all duration-150 ease-out motion-reduce:transition-none">
            <p :class="item.tipo === 'error' ? 'text-error-ink' : 'text-ink'" class="break-words pr-md text-body-sm font-medium" x-text="item.titulo"></p>
            <p class="break-words text-caption text-muted" x-show="item.descripcion" x-text="item.descripcion"></p>
            <button type="button" x-on:click="quitar(item.id)" x-on:pointerdown.stop
                    class="absolute right-xs top-xs flex size-5 cursor-pointer items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-soft hover:text-ink">
                <x-ui.icon nombre="x-mark" class="h-3.5 w-3.5" />
                <span class="sr-only">Cerrar aviso</span>
            </button>
        </div>
    </template>
</div>
