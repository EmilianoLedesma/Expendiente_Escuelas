# 2026-11-12 — Proyecto Educativo, sección 8: Perfil de Ingreso

Trabajo directo en `master`, sin commit ni push. Acotado, con TDD.

## Qué se construyó

Cuatro textos obligatorios en **dos bloques, uno por pantalla** (a petición del dueño tras la primera versión de una sola página): bloque 1 "Perfil y Mecanismo de Ingreso" (Perfil, Mecanismo) y bloque 2 "Costo e Instrumentos de Evaluación" (Costo, Instrumentos), en el orden del dueño; el agrupamiento 2+2 lo elegí yo. Siguiente valida el bloque actual y no escribe; Anterior no valida; Guardar (bloque 2) valida los cuatro y guarda todo o nada, y si falla lleva al primer bloque con error (mismo patrón que la sección 5). Las pruebas de dos bloques se escribieron antes del código y se vieron fallar.

| Campo | Palabras |
|---|---|
| Perfil de Ingreso | 50 a 150 |
| Mecanismo de Ingreso | 100 a 250 |
| Costo para el Usuario | 30 a 100 |
| Instrumentos de Evaluación para Nuevo Ingreso | 100 a 250 |

- **Decisión:** el dueño no dio rango para "Instrumentos de Evaluación"; se propuso 100 a 250 (parecido a Mecanismo) y lo aprobó. Está anotado en `ReglasIngresoProyecto`.
- Etiquetas y placeholders como los pasó el dueño; contador y tope duro con `x-ui.textarea-palabras`.
- Base: `proyecto_educativo_ingreso`, una fila por `escuela_nivel`, cuatro columnas TEXT (NULL hasta guardar, no vacías).

## Archivos

Migración `2026_11_12_000300_create_proyecto_educativo_ingreso_table.php`; `ProyectoEducativoIngreso`, `DatosIngresoProyecto`, `ReglasIngresoProyecto`, `GuardarIngresoProyecto`, `IngresoProyectoCapturada`, `SeccionesProyectoEducativo::ingreso()` (índice 7); rama en `ProyectoEducativoSeccion` (reutiliza `guardarTexto`), su vista y `ProyectoEducativoIndice`. Sin archivos: la limpieza al purgar ya la cubre el `ON DELETE CASCADE`.

## Evidencia

- `GuardarIngresoProyectoTest` (application) y `Paso3IngresoProyectoTest` (Livewire): **ambos se vieron fallar antes de implementar** (clases y tabla inexistentes).
- Verde, enfocadas: `tests/Feature/Application/ProyectoEducativo` y los tests Livewire de Proyecto Educativo (Ingreso, Curriculum, ProyectoEducativo, Filosofía, Objetivos, Presentación, Evaluación, Enfoque, PAEC): 286/286. Pint limpio, PHPStan 0.
- Cuatro pruebas existentes de "otras secciones" ahora apuntan a la sección 9 (índice 8, 12 restantes, "Perfil de Egreso").
- Migración corrida en la base de desarrollo (autorización permanente del dueño: "After each section run migrations").

## Qué NO se hizo / límites

- **No se vio en navegador.**
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- Brecha previa: `ResumenEliminacionNivel` no cuenta los textos de las secciones 1 a 8.
- `docs/progress.md` no se tocó (regla de un solo escritor).

## Siguiente

Revisión en navegador de las secciones 4 a 8 y sección 9 (Perfil de Egreso).
