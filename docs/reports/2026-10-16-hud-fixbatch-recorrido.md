# Reporte: lote de correcciones del recorrido del HUD SEDEQ

**Fecha:** 2026-10-16
**Rama / worktree:** `hud-sedeq-1`, `C:\Users\Emili\Documents\sedeq-incorporacion-hud`. Sin commit.
**Antecede:** `docs/reports/2026-10-16-hud-roles-asignacion.md` (proyecto 1). Este reporte cubre lo que el propietario pidió al recorrer la aplicación en el navegador (`:8001`, base de pruebas aparte) y lo que se cambió.

## 1. Qué pidió el propietario (notas #1 a #11)

| # | Petición |
|---|---|
| 1, 1a | Distinguir la experiencia SEDEQ de la del solicitante; el nombre del encabezado deja de ser "Trámite de incorporación de escuelas particulares". |
| 2, 2a | Sin barra lateral en `/sedeq` (tablero a todo ancho), ni en `/sedeq/cola` ni en `/sedeq/tramite/{escuela}`; la barra solo en las páginas de cuenta. |
| 3, 3a | Barras de revisores e invitación en una sola página "Revisores" en la barra lateral; el tablero conserva un resumen de carga compacto. |
| 4 | El texto del selector de prioridad chocaba con la flecha. |
| 5 | Resuelto, no era error (Raúl ya tenía un trámite reasignado antes). |
| 6 | Tope de revisor: aviso emergente y la confirmación como ventana modal con animación, no bloque en línea. |
| 7, 7a | Estados vacíos explícitos en la vista de lectura (con datos ya se veía bien). |
| 8 | Al pulsar un documento, ventana modal con vista previa de solo la primera página y botón "Abrir en pantalla completa". |
| 9 | Renombrar "Mi cola" a "Mis Revisiones". |
| 10 | Confirmación de revocar como ventana modal con estilo, no `wire:confirm`. |
| 11 | Revisión en 375 px diferida por el propietario. |

## 2. Qué se cambió, por tarea

- **T1 (#1, #1a, #2, #2a, #9):** encabezado "Revisión de incorporación"; enlace de inicio Tablero / Mis Revisiones, sin "Mis trámites" para roles SEDEQ; `TableroSupervisor` y `ColaRevisor` sin barra lateral; menú: revisor = Mi perfil + Mis Revisiones; "Mi cola" renombrado en menú, título y encabezado. Prueba nueva `ShellSedeqTest`.
- **T2 + T3 (#3, #3a, #4, #6, #7, #10):** nueva página `Revisores` (`/sedeq/revisores`, `rol:supervisor`, con barra lateral: Mi perfil + Revisores) con barras, invitación y Revocar con `<dialog>` que nombra al revisor; el tablero conserva un resumen "Nombre N/5"; selector de prioridad con `pl-sm pr-10`; tope alcanzado = aviso emergente + `<dialog id="sobrecarga">` ("Asignar de todos modos" / "Cancelar"); estados vacíos "Sin datos del responsable legal", "Sin documentos del plantel", "Sin documentos del nivel".
- **T4 (#8):** componente `x-ui.pdf-preview` con ventana modal, portada (página 1) y abanico de hasta 4 páginas más, "Abrir en pantalla completa" (pestaña nueva), pdf.js cargado con `import()` solo al abrir la primera vista previa, y enlace de descarga normal como respaldo sin JS o si falla. Dependencia `pdfjs-dist` 6.3.289. Sin cambios de política, ruta ni controlador.

## 3. Evidencia

Antes de esta ronda de correcciones: suite completa 1490/1490, PHPStan 0 errores, Pint limpio, `npm run build` correcto. Pruebas con `$env:DB_DATABASE='sedeq_incorporacion_testing_ui'`; nunca la base de desarrollo.

## 4. Revisión combinada y ronda de correcciones

La revisión combinada del lote arrojó hallazgos que esta ronda atiende:

- **I-1 (`resources/js/app.js`):** un solo worker de pdf.js compartido (`GlobalWorkerOptions.workerPort`, Vite lo emite como chunk `.js`; se quitó el import `?url`); `documento.destroy()` tras pintar (los canvas conservan sus píxeles); tope de tamaño de canvas (`min(ancho, 1600 de alto) * dpr`); `isEvalSupported: false`.
- **M-1:** "Tablero" solo es `aria-current` en `sedeq.tablero` y `sedeq.tramite`, no en `sedeq.revisores`.
- **M-2:** el `<dialog>` de sobrecarga lleva un contador en `wire:key` (`avisosSobrecarga`), que sube con cada aviso, así un estado repetido vuelve a abrir la ventana (mismo patrón que `quitar-niveles` en Paso 2).
- **M-3:** el nombre del revisor en el aviso emergente se obtiene con `User::whereKey(...)->value('name')` en vez de volver a ejecutar `ListarTablero`. Ningún otro componente Livewire usaba un modelo Eloquent; las reglas de arquitectura solo prohíben la fachada `DB` cruda, y pasaron.
- **M-5 / M-6:** canvas acotado (I-1) y, en `pdf-preview`, el esqueleto deja de brillar y el subtítulo "Cargando…" desaparece tras un fallo.
- **M-8:** `pdfjs-dist` pasó a `devDependencies` (versión fija) y se refrescó `package-lock.json`. La integración continua (`.github/workflows`) usa Node 20 y `pdfjs-dist` 6.3.289 declara `engines: node >=22.13`; no se tocó el flujo, **decide el propietario**.
- **M-10:** prueba nueva: `/sedeq/tramite/{escuela}` no muestra `aria-label="Mi cuenta"` para el supervisor ni para un revisor asignado.
- **Docs (I-3):** esta guía y el reporte del proyecto 1 se actualizaron (Revisores en la barra lateral, ventana modal de revocar, aviso + ventana de sobrecarga, estados vacíos, vista previa de PDF, encabezado con rol, sin barra lateral en las páginas SEDEQ).

## 5. Qué debe revisar una persona en un navegador

- El worker de pdf.js se sirve con tipo MIME de JavaScript (`public/build/assets/pdf.worker.min-*.js`) y la vista previa pinta la portada y el abanico.
- Ventana de sobrecarga: foco inicial en "Cancelar", Escape cancela, y volver a provocar el aviso la reabre.
- Ventana de revocar: nombra al revisor, Escape y "Cancelar" cierran, "Revocar acceso" revoca.
- Gestos de la vista previa: abanico con cursor y con foco de teclado, clic en la pila abre pantalla completa, cierre con Escape, fallo con un PDF dañado.
- **Móvil (375 px) sigue diferido** por el propietario.

## 6. Pendientes conocidos (no se hicieron en esta ronda)

- Foco tras revocar.
- Clic en el relleno del `<dialog>` (el fondo se detecta por `event.target`, el relleno interior cuenta como diálogo).
- Clases de botón duplicadas entre vistas.
- Prueba de ida y vuelta HTTP de la invitación.
- Decisión del propietario: Node 22 en la integración continua.
