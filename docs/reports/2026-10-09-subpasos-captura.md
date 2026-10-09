# Sub-pasos dentro de la captura larga (Infraestructura, Mobiliario, Documentos del responsable)

Fecha: 2026-10-09. Commit `5d37468` en `master` (fast-forward de la rama `worktree-stepper-infra-mobiliario`). Solo presentación: no cambia casos de uso, reglas ni esquema.

## Problema

Las pantallas de Infraestructura (Paso 3.2), Mobiliario (Paso 3.3) y Documentos del responsable (Paso 2.2) apilaban todos sus campos en un solo scroll vertical. El dueño pidió que la captura se haga "dentro del wizard", un bloque a la vez.

## Decisión (aprobada por el dueño en el chat)

Patrón elegido: **sub-pasos con un panel visible a la vez** (de tres opciones: sub-pasos, acordeón, pestañas). Todo ocurre en el cliente con Alpine (que ya viene con Livewire), sin viaje al servidor. Los paneles siguen todos en el DOM, por lo que `wire:model`, los `id` de los campos y las anclas `#clave` del resumen de errores no cambian.

Después de verlo, el dueño pidió que Documentos del responsable muestre **un documento por panel** (primero se había agrupado por tema; se descartó).

## Qué se construyó

- `x-ui.stepper` (envoltura, tira de pasos, estado Alpine) y `x-ui.stepper-panel` (un panel con Anterior/Siguiente), en `resources/views/components/ui/`.
- Opciones del stepper: `completos` (marcas de paso completo calculadas en el servidor; sin ellas, la marca "con datos" sale de los campos del panel) y `compacto` (solo el número en la tira cuando hay muchos pasos; el nombre queda como texto para lector de pantalla y tooltip).
- **Infraestructura:** pasos "Ya registrada" (si existe), una categoría de espacios por paso, Sanitarios (si aplica) y Aulas del nivel. Un solo "Guardar y continuar", en el último panel.
- **Mobiliario:** un paso por sala (más Sala de Usos Múltiples en Inicial). Un solo Guardar, en el último panel.
- **Documentos del responsable:** un paso por documento aplicable, con tira numerada compacta. Cada documento conserva su propio botón de guardado; "Volver al resumen" queda debajo del stepper.
- `error-summary`: al aparecer, avisa al stepper para que cambie al panel del primer error; un clic en un enlace del resumen cambia al panel del campo y lo enfoca.
- Enter dentro del formulario grande avanza al siguiente paso en lugar de guardar de forma prematura. Solo aplica cuando el campo pertenece al formulario que envuelve al stepper, así que en Documentos cada fila sigue guardando su propio formulario con Enter.

## Evidencia

- Pruebas nuevas: una por página en Infraestructura y Mobiliario (tira presente, pasos `data-paso` 0..N, un único "Guardar y continuar" y solo después del último panel); dos en Documentos (un panel por documento aplicable; las marcas de paso completo coinciden con los documentos ya subidos, incluyendo los de ámbito `plantel`).
- Ejecutado: `tests/Feature/Livewire` 244/244, `tests/Feature/Validacion` 47/47, `tests/Feature/Application/Tramite` 103/103, Pint limpio. No se ejecutaron la suite completa ni PHPStan (no cambió PHP).
- TDD: cada prueba nueva se vio fallar por la razón correcta antes de implementar.
- `npm run build` se corrió en el checkout principal tras el merge (`public/build` es local y está ignorado por git).

## Lo que NO se verificó

El dueño levantó una copia en `:8001` desde el worktree y pidió el cambio a un documento por panel; el servidor se detuvo después por poca memoria. **No** se confirmó en navegador: el cambio real de panel, Anterior/Siguiente, el salto al panel con error tras un guardado fallido, Enter para avanzar, ni el diseño a 375 px. PHPUnit solo comprueba el HTML generado, no el comportamiento de Alpine.

## Pendiente / no hecho

- Paso 2.4 "Documentos del nivel" no se tocó (5-8 documentos más un formulario de datos); el dueño no lo pidió.
- Si en navegador los paneles no cambian, revisar primero la carga de Alpine y el `x-data` multilínea de `stepper.blade.php`.
- Clases de Tailwind nuevas (p. ej. en la tira de pasos) necesitan `npm run build` tras cualquier merge que toque vistas.
