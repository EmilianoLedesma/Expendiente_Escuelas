# Sección 14 del Proyecto Educativo: Plan de Estudios (Componente y Enfoque de Formación Laboral)

Fecha: 2026-11-17. Rama: `master` (trabajo directo, sin commit ni push). Solo media superior.

## Qué se construyó

Una sola página ("un solo bloque", como pidió el dueño) titulada "Componente y Enfoque de Formación Laboral" con tres textos y un único "Guardar":

- Formación 1 y Formación 2: requeridas, 100 a 250 palabras cada una.
- Otras formaciones: **opcional**. Vacía es válida y se guarda como NULL; con texto debe tener 100 a 250 palabras.

El dueño no dio límites de palabras ni dijo si "Otras formaciones" era opcional. Ambas cosas son una propuesta mía: el texto dice "cualquier otra formación", y el dueño respondió "One single block" a la propuesta sin objetar el resto, así que se tomó como aprobada.

## Comportamiento

- El guardado es todo o nada: si falla cualquier regla no se escribe nada y se muestran todos los errores.
- Borrar el texto de "Otras formaciones" y guardar lo deja en NULL.
- La sección está **completa** con Formación 1 y 2 válidas y "Otras" vacía o válida. El índice y el menú lateral la marcan igual que las demás.
- Un trámite enviado rechaza el guardado con el aviso habitual. No marca el paso `proyecto_educativo` como completado.

## Backend

- Tabla `proyecto_educativo_formacion_laboral` (migración `2026_11_12_001000`, ejecutada en la base de desarrollo por la regla permanente del dueño): una fila por `escuela_nivel` (`UNIQUE`, `ON DELETE CASCADE`), `formacion_laboral_1` y `_2` `TEXT NOT NULL`, `otras_formaciones` `TEXT NULL`, y un `CHECK` con nombre por columna (no en blanco). El rango de palabras lo valida la capa de aplicación.
- `GuardarFormacionLaboralProyecto`, `ReglasFormacionLaboralProyecto` (`RANGOS` y `OPCIONALES`), `FormacionLaboralProyectoCapturada`, `DatosFormacionLaboralProyecto`, modelo `ProyectoEducativoFormacionLaboral`, `SeccionesProyectoEducativo::planEstudios()` y la entrada en `EstadoSeccionesProyecto`. Los nombres evitan chocar con los modelos `PlanEstudios` del Paso 3.
- La sección usa la ruta genérica de texto de una sola página de `ProyectoEducativoSeccion` (igual que la sección 10), con su rama en la vista.
- Limpieza al purgar un trámite o quitar un nivel: sin cambios (no hay archivos; las filas se borran en cascada).

## Evidencia

- Pruebas escritas primero y vistas fallar por la razón correcta (clases y tabla inexistentes): 16 de aplicación y 8 de Livewire.
- Después: `GuardarFormacionLaboralProyectoTest` y `Paso3FormacionLaboralProyectoTest` en verde (junto con la sección 13: 43 pruebas). Pint limpio, PHPStan 0 errores.
- Regresiones enfocadas: `tests/Feature/Validacion` 71, demás archivos Livewire de Proyecto Educativo 123 + 59 (incluye formación inicial y continua, PAEC, currículum y el menú lateral). Las cuatro pruebas del marcador "Contenido por definir" ahora apuntan a la sección 15 (Mapa Curricular).
- **Defecto encontrado en mis pruebas:** el caso "la base rechaza texto en blanco" insertaba filas inválidas en un bucle dentro de la transacción de la prueba; en Postgres el primer error aborta la transacción y los siguientes inserts también fallaban, así que el `catch` pasaba por una causa equivocada y solo se verificaba de verdad el primer campo. Corregido con un savepoint por insert (`DB::transaction`) en la prueba de la sección 14 y en la de la sección 13 (que tenía el mismo defecto). Las pruebas de CHECK de secciones anteriores (por ejemplo `GuardarAcademiasProyectoTest`) usan el mismo patrón y no se revisaron.
- La suite completa no se corrió localmente (regla del dueño); falta el veredicto de CI. Nada se probó en un navegador.

## Pendiente

- Recorrido en navegador de las secciones 4 a 14.
- Las pruebas de CHECK con bucle de inserts en secciones anteriores pueden tener el defecto descrito arriba.
- Sigue sin haber descarga de PDF ni vista de revisor para los archivos del PAEC y de la planeación de formación continua; `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 14.
- Siguiente: sección 15 (Mapa Curricular), luego 16 a 20; después hacer completable el paso, el plan del CCT provisional y la visibilidad de trámites aprobados.
