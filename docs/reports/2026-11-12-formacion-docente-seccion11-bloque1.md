# 2026-11-12 — Proyecto Educativo, sección 11: Formación Docente, bloque 1 (Formación Docente Inicial)

Trabajo directo en `master`, sin commit ni push. Acotado, con TDD. La sección es grande: el dueño la entrega por bloques; este es el primero.

## Qué se construyó

Pantalla 1: **Política Global de Formación Inicial** (100 a 250 palabras). Luego **una acción por pantalla** ("Acción k de n"), que se agregan y se quitan (empieza con 1, máximo 20, mínimo 1). Siguiente valida solo la pantalla actual y no escribe; Anterior no valida; Guardar (última pantalla) valida todo y guarda todo o nada, y si falla lleva a la primera pantalla con error.

Campos de cada acción (todos obligatorios salvo Otros). Los límites de palabras de los textos **no los dio el dueño: son una propuesta que aprobó**:

| Campo | Entrada | Regla |
|---|---|---|
| Nombre de la acción | texto | hasta 150 caracteres |
| Propósito / Participantes / Temario / Evaluación / Recursos / Perfil de ponente(s) / Reconocimiento | texto con contador | 10-100 / 5-50 / 20-150 / 10-100 / 5-100 / 10-100 / 3-50 palabras |
| Formato | radio: Taller, Curso, Seminario, Diplomado, Otro | "Otro" abre "Especifique el formato" (obligatorio, hasta 60 caracteres); con otro formato el texto sobrante se descarta |
| Duración | `<input type="number">` con "horas" | entero de 1 a 999 |
| Horario | 7 casillas de día + dos `<input type="time">` | al menos un día, sin repetir; fin posterior al inicio |
| Periodo | dos `<input type="date">` | fin no anterior al inicio |
| Otros | texto con contador | opcional, hasta 100 palabras |

Petición del dueño: horas y fechas ya no son texto libre sino campos nativos (formato `HH:MM` y `AAAA-MM-DD`, validados también en el servidor).

## Base de datos

`2026_11_12_000600_create_proyecto_educativo_formacion_tables.php`: `proyecto_educativo_formacion_politica` (una fila por nivel y `tipo`) y `proyecto_educativo_formacion_acciones` (una fila por acción, con `orden`). `tipo` ('inicial' hoy) deja reutilizar las tablas en los bloques siguientes **si tienen la misma forma (suposición mía, sin confirmar)**. Horas TIME, fechas DATE, días JSONB. CHECK en la base: formato de la lista, "Otro" con su texto (y solo entonces), duración 1-999, días como lista de 1 a 7, fin de hora posterior al inicio, fin de fecha no anterior, orden único. Guardar reemplaza la lista de acciones (borra y vuelve a insertar, en una transacción).

## Archivos

Modelos `ProyectoEducativoFormacionPolitica` y `ProyectoEducativoFormacionAccion`; `DatosFormacionInicialProyecto`, `ReglasFormacionProyecto`, `GuardarFormacionInicialProyecto`, `FormacionInicialProyectoCapturada`, `SeccionesProyectoEducativo::formacion()` (índice 10); `ProyectoEducativoSeccion` (props `politica_inicial` y `acciones`; acciones `siguienteFormacion`, `anteriorFormacion`, `agregarAccion`, `quitarAccion`), su vista y `ProyectoEducativoIndice`. El componente compartido `x-ui.textarea-palabras` ganó la propiedad `optional` (por defecto falso: las otras secciones no cambian) para que "Otros" no se marque como obligatorio.

## Evidencia

- `GuardarFormacionInicialProyectoTest` (38) y `Paso3FormacionInicialProyectoTest` (17): **se escribieron antes del código y se vieron fallar** (47 pruebas, 1 pasaba).
- Verde, enfocadas, en tres tandas: `tests/Feature/Validacion` 71, `tests/Feature/Application/ProyectoEducativo` 240, Livewire de Proyecto Educativo restantes 81, más Formación/ProyectoEducativo/Filosofía/Objetivos/Presentación 67. Pint limpio, PHPStan 0.
- Una corrida en una sola tanda terminó con "Premature end of PHP process" en la prueba de PDF de más de 10 MB (el fallo conocido de correr muchas pruebas juntas); no se tomó como evidencia y se repitió por partes.
- Cuatro pruebas existentes de "otras secciones" ahora apuntan a la sección 12 (índice 11, 9 restantes, "Programa de Academias Docentes").
- Migración corrida en desarrollo (autorización permanente del dueño).

## Qué NO se hizo / límites

- **No se vio en navegador**: sobre todo los campos nativos de hora y fecha (el formato que se muestra depende del idioma del navegador), la casilla de días con `wire:model` sobre arreglo, y el contador por `entangle` con ruta con puntos (`acciones.0.proposito`), igual que en la sección 4.
- La etiqueta Completa del índice significa "bloque 1 completo": cuando lleguen los demás bloques de la sección habrá que exigirlos todos.
- Los otros bloques de la sección 11 (el dueño los envía después).
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- Brecha previa: `ResumenEliminacionNivel` no cuenta los textos de las secciones 1 a 11.
- `docs/progress.md` no se tocó en esta sección.

## Siguiente

Bloques siguientes de la sección 11 (los envía el dueño); revisión en navegador de las secciones 4 a 11.
