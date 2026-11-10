# Sección 2 del Proyecto Educativo: Filosofía Institucional (media superior)

Fecha: 2026-11-10. Misma rama que la sección 1 (`worktree-presentacion-seccion1`). Sigue el patrón de `2026-11-10-presentacion-proyecto-seccion1.md`.

## Sección 2

- **Tabla** `proyecto_educativo_filosofia` (migración `2026_11_10_000100_create_proyecto_educativo_filosofia_table.php`): una fila por `escuela_nivel` (`UNIQUE` con nombre `pe_filosofia_nivel_unico`, FK `ON DELETE CASCADE`), columnas `mision`, `vision`, `valores` de tipo `TEXT NULL` (borrador), y CHECK con nombre que impiden guardar texto en blanco. Modelo `ProyectoEducativoFilosofia`.
- **Application** (mismo esquema que la sección 1): `DatosFilosofiaProyecto` (campos derivados del constructor), `ReglasFilosofiaProyecto` (una sola lista de rangos: misión 50-150, visión 50-150, valores 50-250), `GuardarFilosofiaProyecto` (compuerta de Paso 3, bloqueo del nivel, `TramiteEditable::asegurarEscuela`, `updateOrCreate`) y `FilosofiaProyectoCapturada` (`datos()`, `completa()`). No marca el paso `proyecto_educativo` como completado.
- **Conteo de palabras compartido**: nueva `ConteoPalabras` (una sola definición: trozos separados por espacio en blanco, NBSP incluido). Ofrece `contar()`, la regla de rango `regla($min, $max, $completa)` y `errores($rangos, $datos, $completa)` para los campos que solo tienen rango de palabras. La sección 1 pasó a usarla (se quitó `ReglasPresentacionProyecto::contarPalabras`); su comportamiento no cambió, solo el texto del mensaje de la síntesis, que ahora es genérico ("Debe tener entre 500 y 1000 palabras (lleva N)").
- **Contador del navegador**: componente `x-ui.textarea-palabras` (campo + contador en vivo con el rango). Es la única copia del conteo en JS (`split(/\s+/)`), que cuenta igual que PHP; la síntesis de la sección 1 y los tres campos de la sección 2 lo usan.
- **Slug**: `SeccionesProyectoEducativo::filosofia()` (sin copia fija en Livewire).
- **UI**: una sola página (sin pasos), tres textareas con las etiquetas y placeholders del dueño y un botón "Guardar". El índice muestra "Completa"/"Pendiente" para esta sección según la fila guardada.

## Decisión: borrador contra completa (igual que la sección 1)

El guardado es de borrador: **el máximo de palabras de lo capturado se exige siempre** (pasarse de 150 o 250 se rechaza y no escribe), y **lo requerido y el mínimo solo cuentan para "completa"**. Un texto de 49 palabras se guarda como borrador pero la sección sigue "Pendiente". En la sección 1 el último "Guardar" exige el mínimo porque es el paso final de un flujo; en una página única no hay un paso final que lo justifique, así que "Guardar" nunca bloquea un borrador corto y el aviso lo dice: "Sección completa" o "Borrador guardado: aún faltan campos o palabras". Si el dueño prefiere que "Guardar" exija el mínimo, es cambiar `completa: false` por `true` en `GuardarFilosofiaProyecto` y sus pruebas.

## Evidencia

Pruebas escritas primero (clases inexistentes) y vistas fallar.

- `tests/Feature/Application/ProyectoEducativo/GuardarFilosofiaProyectoTest.php`: límites 49/50/150/151 (misión y visión) y 49/50/250/251 (valores) en borrador y completa, solo espacios (se guarda `NULL`), borrador parcial, una fila por nivel y segundo guardado actualiza, UNIQUE de la base, no marca el paso, trámite enviado con `etapaFaltante === TramiteEditable::ENVIADO`, paso previo rechaza, conteo compartido, campos del DTO.
- `tests/Feature/Livewire/Tramite/Paso3FilosofiaProyectoTest.php`: etiquetas y placeholders, abre con lo guardado, toasts de borrador/completa, máximo excedido, enviado, las demás secciones con marcador, 403 de otro solicitante, 404 en primaria, etiqueta del índice.
- Se actualizaron las pruebas del esqueleto que usaban la sección 2 como marcador (ahora usan la sección 4 y descuentan las secciones 1 a 3).
- Solo directorios enfocados, un archivo a la vez, en `sedeq_incorporacion_testing_ui`. Pint y PHPStan limpios.

## Pendiente

- Verificar en navegador el contador de palabras (no se usó navegador).
- Aplicar la migración `2026_11_10_000100_create_proyecto_educativo_filosofia_table.php` al esquema de las instancias de desarrollo (la hace el controlador).
