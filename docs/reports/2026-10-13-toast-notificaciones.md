# Notificaciones toast en toda la app — 2026-10-13

Rama `worktree-agent-ae0a773b054a8af9c` (worktree aislado, sin commits). Se porta a Blade + Alpine el toast React (Base UI) que pidió el dueño; la app es Laravel + Livewire 3 + Alpine + Tailwind, sin React, y no se cambia el stack.

## Qué se construyó

- **`<x-ui.toast-region />`** (`resources/views/components/ui/toast-region.blade.php`), montado una vez en `layouts.tramite`, `layouts.guest` y `layouts.app`.
  - Pila fija abajo a la derecha (`w-80`, `max-w-[calc(100vw-2rem)]`), tarjeta con borde, sombra, ícono por tipo, título, descripción opcional y botón de cerrar. Entrada y salida deslizando y desvaneciendo 150 ms (`motion-reduce:transition-none`).
  - Éxito e info se cierran solos a los 5 s; los errores esperan a que se cierren. Región `aria-live="polite"` con `aria-label="Notificaciones"`; cada error lleva `role="alert"`.
  - Usa los tokens del repo (`border-l-success-ink`, `text-muted`, etc.), no `grayscale-*`. Se confirmó en `npm run build` que todas las clases compilan.
- **Contrato de eventos:** evento del navegador `toast` con `{ tipo: success|error|info, titulo, descripcion? }`.
- **`App\Livewire\Concerns\NotificaToast`** (trait):
  - `toast($titulo, $descripcion = null, $tipo = 'success')`: despacha el evento.
  - `toastAlRedirigir(...)`: deja el aviso en la sesión (`toast`) para la página siguiente; se usa cuando la acción redirige, porque el layout no se vuelve a pintar en un viaje de Livewire y un evento se perdería al navegar.
  - `toastDatosInvalidos()`: aviso de error "No se guardó / Revisa los campos marcados." (los errores por campo y el resumen de errores se conservan).
- **Flash de sesión:** la región pinta `session('toast')` (uno o varios) en la carga. El `status` heredado se mapea a un toast de éxito; el código técnico `verification-link-sent` de Breeze se traduce a "Enlace enviado".

## Dónde se aplicó

| Área | Éxito | Error |
| --- | --- | --- |
| Paso 1 | flash "Plantel guardado" | `PlantelNoDisponible`, `DatosInvalidos`, `InvalidArgumentException` |
| Paso 2 responsable y niveles | flash o evento según redirija | `DatosInvalidos`, niveles inválidos |
| Paso 2.2 documentos (11 acciones, una sola cola) | evento "Documento guardado"; flash "Documentos completos" al terminar; flash al volver a validación | `DatosInvalidos`, plantel de otro trámite ya enviado |
| Paso 2.4 | datos del nivel, documento, recibo de pago | `DatosInvalidos`, formato antes de los datos |
| Paso 3 (inmueble, infraestructura, mobiliario, plan, plantilla, matrícula) | flash por paso | `DatosInvalidos`, plantel compartido ya enviado, mobiliario vacío |
| Responsables por nivel | invitación enviada, acceso revocado | revocación rechazada, `DatosInvalidos` |
| Validación final | "Validación actualizada"; flash "Trámite enviado a SEDEQ" | "No se pudo enviar el trámite" |
| Eliminar trámite (controlador) | flash "Trámite eliminado" | flash de error; trámite inexistente → info |
| Verificar correo (controlador) | flash "Correo verificado" | — |
| Olvidé mi contraseña, reenviar verificación | evento "Enlace enviado" | — |

Se quitaron las alertas de éxito en línea que el toast reemplaza: `tramite/index`, `tramite/resumen`, `responsables-nivel`, `login`, `forgot-password`, `verify-email`. Se conserva el banner de resultado de `validacion-final` (no es un aviso de acción).

## Decisiones

- **No se usó el sistema de notificaciones de Filament**: va atado al tema del panel `/admin` y no carga del lado del solicitante.
- **Los errores de un caso de uso mantienen el error en línea**; el toast solo avisa. Los errores de validación de formulario (`validate()`) no generan toast: el resumen de errores ya los cubre.
- **Redirecciones → flash, páginas que se quedan → evento.** `Paso2Documentos::avanzar()` decide cuál: nunca se emiten los dos.
- **Cambio en `routes/web.php`:** solo la línea del `missing()` de `tramite.eliminar` (ahora flash `toast` tipo info). La ruta `profile` no se tocó.
- Base de datos de pruebas `sedeq_incorporacion_testing_ui` (ya existía) por variable de entorno, para no chocar con la sesión del perfil. No se creó nada.

## Evidencia

- Suite completa: **1232/1232** (4065 aserciones). Pint limpio. PHPStan nivel 5 + reglas PHPat: 0 errores. `npm run build` correcto.
- Pruebas nuevas: `tests/Feature/View/ToastRegionTest.php` (7), `tests/Feature/Livewire/NotificaToastTest.php` (3), más aserciones de toast en las pruebas de Paso 1, 2, 2.2, 2.4, 3.x, responsables, validación final, eliminar, envío y auth. Cada comportamiento nuevo se vio fallar antes de implementarse, con una excepción: las aserciones de Paso 2 responsable se escribieron antes pero no se corrieron en rojo; pasaron en verde al implementar.
- Pruebas existentes actualizadas porque comprobaban la alerta en línea o `session('status')`: `EliminarTramiteTest`, `EnvioTramiteTest`, `ResponsablesNivelPaginaTest` (la prueba "los avisos no se filtran" ahora comprueba que el aviso viaja por evento y no queda en sesión).

## Lo que no se hizo / no se verificó

- **Ningún navegador:** no se vio la animación, el cierre automático, el botón de cerrar, el apilado ni 375 px. Las pruebas comprueban el HTML de la región y los eventos, no su comportamiento en Alpine.
- **Alpine llega con Livewire.** `layouts.tramite` carga `@livewireScripts`; `guest` y `app` dependen de que la página tenga un componente Livewire (todas las pantallas actuales de auth y el perfil lo tienen). Una página estática futura con esos layouts mostraría la región vacía; habría que añadir `@livewireScripts` ahí.
- `update-profile-information-form` (Breeze, del trabajo del perfil) sigue mostrando su propio texto con `session('status') === 'verification-link-sent'`; no se tocó. Si el perfil usa `toast()` ahí, se puede quitar ese bloque.
- Swipe para descartar (presente en el componente React): no se portó.
- Sin commits ni cambios en `docs/progress.md`.
