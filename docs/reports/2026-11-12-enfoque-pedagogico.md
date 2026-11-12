# 2026-11-12 — Proyecto Educativo, sección 4: Enfoque Pedagógico

Rama `worktree-enfoque-pedagogico` (worktree `.claude/worktrees/enfoque-pedagogico`), sin fusionar ni subir. Trabajo acotado, igual que las secciones 2 y 3, hecho con TDD (las pruebas se vieron fallar por la razón correcta antes de escribir el código).

## Qué se construyó

Sección 4 del Proyecto Educativo (solo `media_superior`), con **un solo campo visible por pantalla** (pedido del dueño):

| Grupo | Campo | Regla |
|---|---|---|
| 1. Propuesta Global del Proyecto | `propuesta_global` | 100 a 200 palabras |
| 2. Apropiación del Enfoque Pedagógico-Didáctico | `apropiacion_enfoque` | 450 a 600 palabras |
| 3. Estrategias y Actividades Didácticas Principales | `estrategias_centrales` y `actividades_sustantivas` | 2 a 5 ejemplos cada lista, un ejemplo por pantalla, **cada ejemplo de 20 a 80 palabras** |

- Etiquetas y placeholders copiados tal cual del texto del dueño.
- Las dos listas son independientes (no son pares estrategia-actividad). Es una lectura de la respuesta del dueño: "Estrategias Centrales" y "Actividades Sustantivas" aparecen como dos subgrupos separados.
- Los límites de palabras por ejemplo (20 a 80) los propuso el asistente cuando el dueño pidió "un límite de palabras coherente"; el texto original no los trae. Si SEDEQ define otros, se cambian en `ReglasEnfoqueProyecto::PALABRAS_EJEMPLO`.
- "Siguiente" valida solo el campo de la pantalla actual y no escribe nada. "Guardar" (último campo) valida todo y guarda todo o nada; si falla, lleva a la primera pantalla con error. "Añadir otro ejemplo" suma uno vacío (hasta 5) y pasa a él; "Quitar este ejemplo" borra el actual (hasta dejar 2).

## Archivos

- Migración `2026_11_11_000000_create_proyecto_educativo_enfoque_table.php`: una fila por `escuela_nivel`; las listas son `JSONB` con CHECK de forma (arreglo de 2 a 5 elementos).
- `ProyectoEducativoEnfoque` (modelo), `DatosEnfoqueProyecto` (DTO), `ReglasEnfoqueProyecto` (única lista de reglas), `GuardarEnfoqueProyecto` (caso de uso, mismas compuertas que la sección 3), `EnfoqueProyectoCapturada` (lectura y `completa()`), `SeccionesProyectoEducativo::enfoque()`.
- `ProyectoEducativoSeccion` (acciones `siguienteEnfoque`, `anteriorEnfoque`, `agregarEjemplo`, `quitarEjemplo`, rama en `guardar`), su vista y `ProyectoEducativoIndice` (etiqueta Completa/Pendiente de la sección 4).
- Pruebas nuevas: `GuardarEnfoqueProyectoTest` (19) y `Paso3EnfoqueProyectoTest` (17). Cuatro pruebas existentes (`Paso3FilosofiaProyectoTest`, `Paso3ObjetivosProyectoTest`, `Paso3PresentacionProyectoTest`, `Paso3ProyectoEducativoTest`) usaban la sección 4 como ejemplo de "Contenido por definir"; ahora apuntan a la sección 5.

## Evidencia

- Pruebas enfocadas: Enfoque (Livewire) 17/17, `tests/Feature/Application/ProyectoEducativo` 79/79, las cuatro pruebas existentes tocadas 10 + 10 + 21 + 10, `Paso3RequierePaso2CompletoTest` 8, `tests/Feature/Validacion` 71 (incluye la guarda de `wire:model.blur`), `ProyectoEducativoEnTramiteTest` 7, `EstadoPaso3Test` 9, `PasosCapturaSeederTest` 2, `SedeqSoloLecturaTest` 2. Pint limpio, PHPStan 0 errores (con las reglas PHPat).
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- Una falla real encontrada por las pruebas y corregida: al abrir la sección sin nada guardado, `cargarEnfoque()` convertía `null` a `''` y lo asignaba a una propiedad de tipo arreglo (error 500). PHPStan además obligó a corregir el tipo de las propiedades: llegan del navegador y no siempre son una lista.

## Qué NO se hizo / límites

- **No se vio en un navegador.** En particular, el contador de palabras enlaza con `$wire.entangle('estrategias_centrales.0')` (ruta con punto) y los `id` de los campos llevan punto; las pruebas de Livewire no ejecutan Alpine. Hay que revisarlo a mano antes de dar la sección por buena.
- La migración **no se ha corrido en la base de desarrollo** (solo en la de pruebas, vía `RefreshDatabase`); se corre con la autorización explícita del dueño.
- La sección no hace que el paso `proyecto_educativo` quede completado (sigue igual que 1-3).
- `docs/progress.md` no se tocó (regla de un solo escritor): se actualiza al fusionar, a partir de este reporte.
- Sin commit ni push: el dueño no lo ha pedido.

## Sección 5: Modelo de Evaluación (misma rama)

Siete textos obligatorios, todo o nada al guardar, mostrados **por bloque** (un bloque por pantalla; el dueño eligió "los 3 grupos con encabezado", no un campo por pantalla):

| Bloque | Campos | Rango |
|---|---|---|
| 1. Funciones del Proceso Evaluativo | Evaluación Diagnóstica, Formativa, Sumativa | 100 a 300 palabras cada una |
| 2. Estrategias de Evaluación Auténtica | Heteroevaluación, Coevaluación, Autoevaluación | 100 a 300 palabras cada una |
| 3. Elementos y Porcentajes para la Calificación | Elementos y porcentajes | 200 a 500 palabras |

- El dueño nombró tres bloques como "Funciones del proceso evaluativo", "Evaluación Formativa" y "Estrategias de Evaluación Auténtica"; "Evaluación Formativa" es un campo del primer grupo, no un grupo. Se le preguntó y confirmó los tres grupos con encabezado.
- "Siguiente" valida solo los campos del bloque actual y no escribe; "Guardar" (bloque 3) valida los siete y, si falla, muestra el primer bloque con error.
- Archivos: migración `2026_11_12_000000_create_proyecto_educativo_evaluacion_table.php` (siete columnas TEXT, una fila por nivel), `ProyectoEducativoEvaluacion`, `DatosEvaluacionProyecto`, `ReglasEvaluacionProyecto` (`RANGOS` y `BLOQUES`), `GuardarEvaluacionProyecto`, `EvaluacionProyectoCapturada`, `SeccionesProyectoEducativo::evaluacion()`; la página reutiliza el camino de texto (`esTexto`) del componente y agrega `siguienteEvaluacion` / `anteriorEvaluacion`.
- Pruebas: `GuardarEvaluacionProyectoTest` (25 con los casos por campo) y `Paso3EvaluacionProyectoTest` (11). Las cuatro pruebas de "otras secciones" ahora apuntan a la sección 6.
- Evidencia: `tests/Feature/Application/ProyectoEducativo` 104/104, Livewire de Proyecto Educativo 17 + 11 + 10 + 10 + 21 + 10 + 8, `tests/Feature/Validacion` 71, Pint limpio, PHPStan 0.
- **Las pruebas de la sección 5 se escribieron antes que el código pero no se corrieron en rojo**: pasaron en la primera corrida con todo implementado. La sección 4 sí se vio fallar primero.
- Migración de la sección 4 corrida en la base de desarrollo con autorización del dueño (2026-11-12, `migrate`, solo esa migración pendiente). **La migración de la sección 5 no se ha corrido en desarrollo.**
- Tampoco se vio en navegador.

## Siguiente

Revisión en navegador de las secciones 4 y 5 (y de las secciones 1-3, pendientes desde el 2026-11-10); decisión del dueño sobre los 20-80 palabras por ejemplo; luego secciones 5-20 (el dueño entrega los campos de cada una).
