# Sección 3 del Proyecto Educativo: Objetivos Institucionales (media superior)

Fecha: 2026-11-10. Misma rama que las secciones 1 y 2. Sigue el patrón de `2026-11-10-presentacion-proyecto-seccion2.md`.

## Sección 3

- **Tabla** `proyecto_educativo_objetivos` (migración `2026_11_10_000200_create_proyecto_educativo_objetivos_table.php`): una fila por `escuela_nivel` (`UNIQUE` con nombre `pe_objetivos_nivel_unico`, FK `ON DELETE CASCADE`), `objetivo_general` y `objetivos_especificos` de tipo `TEXT NULL` (borrador) y CHECK con nombre contra texto en blanco. Modelo `ProyectoEducativoObjetivos`.
- **Application**: `DatosObjetivosProyecto`, `ReglasObjetivosProyecto` (rangos: objetivo general 50-100, específicos 100-250), `GuardarObjetivosProyecto` y `ObjetivosProyectoCapturada`, con el mismo esquema, compuerta y guarda `TramiteEditable` que las secciones 1 y 2. No marca el paso `proyecto_educativo` como completado.
- **Código compartido, una sola vez**: las reglas de rango de palabras de las secciones 2 y 3 salen de `ConteoPalabras::errores($rangos, $datos, $completa)` (creada con la sección 2); cada clase `Reglas*` es solo su tabla de rangos. Las secciones de una sola página (2 y 3) comparten en el componente Livewire un único método de guardado (`guardarTexto`, con un `match` por sección que entrega acción, DTO y lectura) y una sola vista de campo (`x-ui.textarea-palabras`). El comportamiento de las secciones 1 y 2 no cambió.
- **Slug**: `SeccionesProyectoEducativo::objetivos()`.
- **UI**: una sola página con las etiquetas y placeholders del dueño ("Objetivo General (50 a 100 palabras)", "Objetivos Específicos (100 a 250 palabras)") y contador de palabras en vivo con el rango en cada campo. El índice muestra "Completa"/"Pendiente" para esta sección.

## Decisión: borrador contra completa (igual que la sección 2)

El máximo de palabras de lo capturado se exige siempre (pasarse se rechaza y no escribe); lo requerido y el mínimo solo cuentan para "completa". "Guardar" nunca bloquea un borrador corto y avisa "Sección completa" o "Borrador guardado: aún faltan campos o palabras".

## Evidencia

Pruebas escritas primero y vistas fallar.

- `GuardarObjetivosProyectoTest`: límites 49/50/100/101 (general) y 99/100/250/251 (específicos) en borrador y completa, solo espacios, borrador parcial, una fila por nivel y segundo guardado actualiza, UNIQUE de la base, no marca el paso, trámite enviado con `etapaFaltante === TramiteEditable::ENVIADO`, paso previo rechaza.
- `Paso3ObjetivosProyectoTest`: etiquetas y placeholders, abre con lo guardado, toasts de borrador/completa, máximo excedido, enviado, las demás secciones con marcador, 403 y 404 de primaria, etiqueta del índice.
- Las pruebas que daban por marcador a la sección 3 pasaron a la sección 4 (esqueleto, sección 1 y sección 2).
- Solo directorios enfocados, un archivo a la vez, en `sedeq_incorporacion_testing_ui`. Pint y PHPStan limpios.

## Pendiente

- Verificar en navegador el contador de palabras.
- Aplicar la migración `2026_11_10_000200_create_proyecto_educativo_objetivos_table.php` a las instancias de desarrollo (la hace el controlador).

## Revisión 3

Instrucción del dueño: "Enforce rules on inputs". Esta revisión **sustituye** lo que arriba se dice del guardado como borrador en las secciones de una sola página.

- **Servidor**: el "Guardar" de las secciones 2 y 3 exige todas las reglas (requeridos, mínimo y máximo de palabras), todo o nada: si algo falla no se escribe nada y se muestran todos los errores (toast "No se guardó"). Un guardado válido avisa "Sección completa". Se eliminó la vía de borrador y el aviso "Borrador guardado"; `ReglasFilosofiaProyecto::errores()`/`ReglasObjetivosProyecto::errores()` y `ConteoPalabras::errores()` ya no tienen bandera `completa`. "Completa" sigue leyéndose con las mismas reglas.
- **Sección 1**: "Siguiente" sigue exigiendo requeridos y formatos del paso (y guarda el resto como borrador, necesario para ir de paso en paso); el "Guardar" del paso 3 ahora exige **todo** el formulario y, si el error cae en un paso que no se está pintando, lleva al primer paso con errores.
- **Cliente** (solo ayuda; el servidor manda): `maxlength` igual a los máximos de las reglas en todos los textos (255/500/255/255/255/100, y 20 en teléfonos por la máscara y la lada), `type=email`, `type=url` con placeholder `https://...`, `inputmode="tel"` y `autocomplete` (`tel-national`, `email`, `organization`, `street-address`, `name`), fechas con `min="1900-01-01"` y `max` = hoy en la de apertura, atributo `required` en los obligatorios y selección con radios para los enums. En `x-ui.textarea-palabras`: contador en vivo que se pone en rojo por debajo del mínimo (ya con texto) o por encima del máximo, y tope duro: al teclear o pegar más del máximo se recorta a ese número de palabras; el valor que va a Livewire es el mismo que se ve (una sola variable `texto` enlazada con `entangle`).
- **Pruebas**: fallas del guardado estricto por sección en cada frontera (no escribe nada), textos de aviso, atributos renderizados (`maxlength`, `type`, `min`/`max`, `inputmode`, `autocomplete`, `required`), y que la vía de borrador ya no existe.
- Sigue sin verificarse en navegador el recorte y el contador (no se usó navegador).
