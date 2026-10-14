# Botón con estado para los correos — 2026-10-13

Rama `boton-estado`. Se porta a Blade + Alpine el *Stateful Button* de Aceternity UI (sin React ni dependencias nuevas) y se usa en los tres botones que envían correo.

## Qué se construyó

- **`<x-ui.stateful-button>`** (`resources/views/components/ui/stateful-button.blade.php`).
  - Props: `action` (nombre del método de Livewire), `label`, `loading-label` («Enviando...»), `success-label`, `error-label`, `disabled`, `reset-after` (ms), `type` y clases extra (se mezclan en el `<button>`).
  - Estados Alpine por instancia: `idle`, `loading`, `success`, `error`. La acción se espera con `await this.$wire.$call(action)`.
  - **Contrato:** solo `true` es éxito. `false`, `null`/`undefined` (por ejemplo una validación fallida, que aborta la acción) o una excepción dan error, y desde el error se puede reintentar con otro clic. No hay lógica de correo dentro del botón.
  - **Éxito = «quedó en cola»**, no «llegó». Por eso el texto es «Solicitud de envío realizada».
  - `reset-after`: regresa a reposo tras un éxito. Solo existe si se pasa; las tres páginas usan 4000 ms (decisión del dueño). El error no se reinicia solo.
- **Animación.** El cargador entra de ancho 0 a 20 px y de escala 0 a 1 (200 ms) y gira con `animate-spin` (1 s, lineal, infinito) mientras dure la petición. Al terminar sale encogiéndose y entra el icono de éxito (círculo con paloma) o de error (círculo con X). Se usó la geometría SVG original del cargador y del check.
- **Colores del sistema, sin `green-500`.** Reposo y carga: `bg-primary` con `hover:bg-primary-active`; éxito: `bg-success-ink`; error: `bg-error-ink`; texto `text-on-primary`; mismo `rounded-md`, `min-h-11`, tipografía y `focus-visible` que `x-ui.button-primary`. Los tokens `-ink` cumplen contraste (el mismo par ya lo usa `hold-button`). Ningún hex nuevo.
- **Sin saltos de diseño.** Las cuatro etiquetas comparten una celda de `inline-grid`; el botón mide la más larga y no se mueve al cambiar de estado.
- **Accesibilidad.** `<button>` real; `aria-busy` y `aria-disabled` durante la carga (no `disabled`, para no perder el foco); región `role="status" aria-live="polite"` con el texto del estado; iconos `aria-hidden`; el estado se dice con texto e icono, no solo con color. Con `prefers-reduced-motion` el giro y las transiciones se apagan y quedan el arco fijo y el texto «Enviando...».
- **`wire:ignore` en la raíz.** El estado vive solo en Alpine; así un re-render de Livewire no pisa las clases ni reinicia el botón. Costo: `disabled`/etiquetas no cambian desde el servidor tras el primer pintado (no hace falta hoy).

## Dónde se aplicó

| Pantalla | Acción | Cambio de contrato |
| --- | --- | --- |
| Verifica tu correo (`verify-email`) | `sendVerification` | `void` → `bool`; `true` también si el correo ya estaba verificado (redirige, no es error) |
| Recuperar contraseña (`forgot-password`) | `sendPasswordResetLink` | `void` → `bool`; `false` si el broker no envió; el botón es `type="submit"` y el formulario ya no usa `wire:submit` (el clic, o Enter en un campo, lo maneja el botón) |
| Responsables por nivel (invitar) | `invitar` | `void` → `bool`; `false` si `DatosInvalidos` |

Los avisos (`toast`) y los errores por campo se conservan; el botón solo añade el estado en el propio botón. El registro (`register`) no tiene botón de reenvío propio.

## Evidencia

- **Pruebas nuevas, vistas fallar antes (0 de 17):** `tests/Feature/View/StatefulButtonTest.php` (13: etiquetas, tokens, cableado a Livewire, anti doble envío, spinner, iconos, error/reintento, instancias independientes, a11y, reducción de movimiento, `reset-after`) y `tests/Feature/Livewire/BotonesDeCorreoTest.php` (4: que cada acción devuelve `true`/`false` según corresponde y que el envío se conserva).
- **Navegador (headless Edge, Alpine real y `$wire` simulado).** Con acciones que resuelven `true`, `false`, lanzan error o nunca terminan: a 600 ms todas en carga con 20 px de icono y `animation: spin 1s linear infinite`; a 2 s éxito `rgb(30,107,52)` y error `rgb(180,35,24)` (los tokens `success-ink` y `error-ink`), la que no termina sigue girando; a 6.5 s la de éxito volvió a reposo y la región `aria-live` anunció cada estado. Con `--force-prefers-reduced-motion` la animación queda en `none` y el indicador sigue visible.
- Suite completa, PHPStan nivel 5 + PHPat y Pint: ver el asiento del `progress.md`.

## Lo que no se verificó

- **No hay pruebas de navegador automatizadas en el repo** (ni Dusk ni Playwright). Lo anterior se hizo con una página de prueba aparte, no con la app real ni con un clic real sobre Livewire.
- **No se vio la animación en movimiento:** las transiciones CSS corren en tiempo real y la captura usa tiempo virtual; se midieron los valores finales con las transiciones apagadas. El suavizado de ancho/escala y el giro real los ve el dueño.
- **Reprocesos de Livewire:** `wire:ignore` evita el problema por diseño, pero no se probó un re-render real con el botón en `loading`.
- **Pasos manuales:** en `/forgot-password` y `/verify-email` (usuario sin verificar) y en Responsables por nivel, con `queue:work` corriendo y `MAIL_MAILER=smtp`: clic → gira «Enviando...» → «Solicitud de envío realizada» y a los 4 s vuelve; con un correo inexistente o inválido → «No se pudo enviar. Reintentar» y se puede volver a intentar.
- El token de color de éxito se tomó de `success-ink`; el diseño no define un par «botón de éxito» propio (hueco documentado, sin inventar paleta).
