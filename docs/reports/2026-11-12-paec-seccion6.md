# 2026-11-12 — Proyecto Educativo, sección 6: Programa Aula, Escuela y Comunidad (PAEC)

Trabajo directo en `master` (a petición del dueño: "stay in main"), sin commit ni push. Acotado, con TDD.

## Qué se construyó

Un solo bloque (todos los campos en la misma página), obligatorios, guardado todo o nada:

| Campo | Regla |
|---|---|
| Diagnóstico para el PAEC | texto, 150 a 500 palabras |
| Hoja de Ruta del PEC | PDF de 1 a 2 páginas |
| Planeación del Abordaje | PDF de 1 a 2 páginas |

- "1 a 2 páginas" no es un conteo de palabras; el dueño eligió **subir PDF** (no texto). El servidor cuenta las páginas reales.
- Cada PDF: máximo 10 MB (igual que el Paso 2), debe empezar con `%PDF-` (no se confía en el nombre ni en el tipo MIME que manda el navegador), debe poder leerse (no dañado ni con contraseña) y tener 1 o 2 páginas. Los mensajes de error van por campo.
- Se valida todo antes de escribir nada. Un archivo nuevo reemplaza al anterior y el anterior se borra **solo si el guardado se confirma**; si una transacción exterior se revierte, los archivos nuevos se borran. Guardar sin volver a subir conserva los archivos ya guardados.
- Los textos de ayuda del dueño ("Describa la hoja de ruta...", "Deberá contemplar...") se muestran como ayuda bajo cada campo de archivo, porque no son placeholders de un cuadro de texto.

## Dependencia nueva

`smalot/pdfparser` ^2.13 (instalada v2.13.1), PHP puro, solo para contar páginas (`App\Infrastructure\Documentos\ContadorPaginasPdf`). Aprobada por el dueño. Sin avisos de seguridad en `composer audit`. **Licencia LGPL-3.0**: durante la conversación se dijo por error que era MIT. Para una aplicación web que no distribuye el paquete no hay obligación adicional, pero queda registrado. Limitación: un PDF de 10 MB con muchas imágenes puede tardar o consumir memoria al analizarse; no se midió.

## Archivos

- Migración `2026_11_12_000100_create_proyecto_educativo_paec_table.php`: una fila por `escuela_nivel`; por archivo, ruta + nombre original + páginas (CHECK: los tres o ninguno, páginas entre 1 y 2).
- `ProyectoEducativoPaec` (con `rutasDe()` para reunir rutas antes de borrar), `DatosPaecProyecto`, `ReglasPaecProyecto`, `GuardarPaecProyecto`, `PaecProyectoCapturada` (no expone rutas de disco), `SeccionesProyectoEducativo::paec()`.
- `ProyectoEducativoSeccion` (usa `WithFileUploads`), su vista, `ProyectoEducativoIndice` (Completa/Pendiente).
- Limpieza de archivos: `PurgarTramite` y `RegistrarNivelesSeleccionados::quitar` borran también los PDF del PAEC; `ResumenEliminacionNivel` los cuenta dentro de "documentos" (si no, el diálogo de confirmación los subcontaría y la guarda "los datos cambiaron desde que confirmaste" no los vería).
- Pruebas nuevas: `GuardarPaecProyectoTest` (27, con PDF reales de dompdf de 1, 2 y 3 páginas, PDF dañado, no-PDF, más de 10 MB, reemplazo, rollback), `Paso3PaecProyectoTest` (9), `tests/Concerns/GeneraPdfs.php`, y 3 pruebas de limpieza en `PurgarTest` y `RegistrarNivelesSeleccionadosTest`. Cuatro pruebas existentes de "otras secciones" ahora apuntan a la sección 7.

## Evidencia

- Pruebas enfocadas: `tests/Feature/Application/ProyectoEducativo` 131/131, Livewire de Proyecto Educativo 9 + 11 + 17 + 10 + 10 + 21 + 10 + 8, `tests/Feature/Archivo` 90/90, `tests/Feature/Application/EscuelaNiveles` 51/51, `tests/Feature/Admin` 62/62, `tests/Feature/Validacion` 71/71, y las otras pruebas que usan purga o el resumen de eliminación (GuardaTramiteEnviado 57, Auditoría 6, Paso 2 niveles/responsable 12 + 13 + 24, Sedeq 8, OrdenPaso2 17, Coherencia 7). Pint limpio, PHPStan 0.
- Las pruebas de aplicación y de limpieza se vieron fallar antes de implementar. **Las de Livewire (9) no se corrieron en rojo**: se escribieron antes que el código del componente pero pasaron en la primera corrida.
- Un fallo de las propias pruebas, corregido en la prueba: el fixture del trámite ya sube 12 documentos al disco falso, así que el ayudante contaba archivos de más; ahora cuenta solo `proyecto_educativo/`.
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.

## Qué NO se hizo / límites

- **No se vio en navegador** (subida real de PDF, barra "Subiendo…", mensajes de error).
- La migración se corrió en la base de desarrollo a petición del dueño (después de escribir este informe).
- No hay descarga de los PDF para el solicitante, el responsable ni el revisor, ni vista del revisor: el dueño eligió "captura + limpieza". Mientras tanto el solicitante ve el nombre y las páginas, pero no puede abrir el archivo que subió.
- Brecha previa, no introducida aquí: `ResumenEliminacionNivel` tampoco cuenta los textos de las secciones 1 a 5 del Proyecto Educativo (se borran por cascade sin aparecer en la confirmación).
- La sección no marca el paso `proyecto_educativo` como completado (igual que las secciones 1 a 5).
- `docs/progress.md` no se tocó (regla de un solo escritor).

## Siguiente

Revisión en navegador de las secciones 4 a 6; descarga y vista del revisor de los PDF (decisión pendiente); sección 7 (Currículum Ampliado), el dueño entrega los campos.
