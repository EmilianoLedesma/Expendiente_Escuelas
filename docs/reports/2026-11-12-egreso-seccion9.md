# 2026-11-12 — Proyecto Educativo, sección 9: Perfil de Egreso

Trabajo directo en `master`, sin commit ni push. Acotado, con TDD.

## Qué se construyó

Un solo campo obligatorio en una página: Perfil de Egreso, de 100 a 400 palabras, con la etiqueta y el placeholder del dueño, contador y tope duro (`x-ui.textarea-palabras`). Guardado como en las secciones 2, 3 y 8 (si la regla falla no se escribe nada; "Sección completa" al guardar bien).

Base: `proyecto_educativo_egreso`, una fila por `escuela_nivel`, una columna TEXT (`perfil_egreso`, NULL hasta guardar, no vacía).

## Archivos

Migración `2026_11_12_000400_create_proyecto_educativo_egreso_table.php`; `ProyectoEducativoEgreso`, `DatosEgresoProyecto`, `ReglasEgresoProyecto`, `GuardarEgresoProyecto`, `EgresoProyectoCapturada`, `SeccionesProyectoEducativo::egreso()` (índice 8); rama en `ProyectoEducativoSeccion` (reutiliza `guardarTexto`), su vista y `ProyectoEducativoIndice`. Sin archivos: la limpieza al purgar ya la cubre el `ON DELETE CASCADE`.

## Evidencia

- `GuardarEgresoProyectoTest` y `Paso3EgresoProyectoTest`: **ambos se vieron fallar antes de implementar** (clases y tabla inexistentes).
- Verde, enfocadas: `tests/Feature/Application/ProyectoEducativo` más los Livewire de Egreso, ProyectoEducativo, Filosofía, Objetivos y Presentación: 249/249. Pint limpio, PHPStan 0. (Las otras pruebas Livewire de Proyecto Educativo no se repitieron esta vez: no tocan código nuevo salvo el índice, cubierto por el test de índice de Egreso.)
- Cuatro pruebas existentes de "otras secciones" ahora apuntan a la sección 10 (índice 9, 11 restantes, "Perfil Docente").
- Migración corrida en desarrollo (autorización permanente del dueño).

## Qué NO se hizo / límites

- **No se vio en navegador.**
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- Brecha previa: `ResumenEliminacionNivel` no cuenta los textos de las secciones 1 a 9.
- `docs/progress.md` no se tocó (regla de un solo escritor).

## Siguiente

Revisión en navegador de las secciones 4 a 9 y sección 10 (Perfil Docente).
