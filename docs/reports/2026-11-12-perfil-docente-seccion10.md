# 2026-11-12 — Proyecto Educativo, sección 10: Perfil Docente

Trabajo directo en `master`, sin push. Acotado, con TDD.

## Qué se construyó

Un solo campo obligatorio en una página: Habilidades y Virtudes Docentes, de 200 a 400 palabras, con la etiqueta y el placeholder del dueño, contador y tope duro (`x-ui.textarea-palabras`). Guardado como en la sección 9 (si la regla falla no se escribe nada; "Sección completa" al guardar bien).

Base: `proyecto_educativo_perfil_docente`, una fila por `escuela_nivel`, una columna TEXT (`habilidades_virtudes`, NULL hasta guardar, no vacía).

## Archivos

Migración `2026_11_12_000500_create_proyecto_educativo_perfil_docente_table.php`; `ProyectoEducativoPerfilDocente`, `DatosPerfilDocenteProyecto`, `ReglasPerfilDocenteProyecto`, `GuardarPerfilDocenteProyecto`, `PerfilDocenteProyectoCapturada`, `SeccionesProyectoEducativo::perfilDocente()` (índice 9); rama en `ProyectoEducativoSeccion` (reutiliza `guardarTexto`), su vista y `ProyectoEducativoIndice`. Sin archivos: la limpieza al purgar ya la cubre el `ON DELETE CASCADE`.

## Evidencia

- Las pruebas de aplicación y Livewire de esta sección se derivaron de las de la sección 9 (mismos casos, rango 200 a 400) y **se vieron fallar antes de implementar** (clases y tabla inexistentes). Las clases de producción también se derivaron de las de la sección 9 con cambio de nombres.
- Verde, enfocadas: `tests/Feature/Application/ProyectoEducativo` más los Livewire de Perfil Docente, ProyectoEducativo, Filosofía, Objetivos y Presentación: 262/262. Pint limpio, PHPStan 0.
- Cuatro pruebas existentes de "otras secciones" ahora apuntan a la sección 11 (índice 10, 10 restantes, "Formación Docente").
- Migración corrida en desarrollo (autorización permanente del dueño).

## Qué NO se hizo / límites

- **No se vio en navegador.**
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- Brecha previa: `ResumenEliminacionNivel` no cuenta los textos de las secciones 1 a 10.
- `docs/progress.md` no se tocó en esta sección; se escribe al cerrar el bloque de secciones.

## Siguiente

Revisión en navegador de las secciones 4 a 10 y sección 11 (Formación Docente).
