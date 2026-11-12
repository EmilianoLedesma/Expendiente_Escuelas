# 2026-11-12 — Proyecto Educativo, sección 12: Programa de Academias Docentes

Trabajo directo en `master`, sin commit ni push. Acotado, con TDD. Sigue el patrón de las secciones 7 y 8 (bloques con "Siguiente" y "Guardar").

## Qué se construyó

Dos bloques, un solo guardado final (todo o nada):

1. **Conformación de la(s) Academia(s) Docente(s)**: "Marque la opción que trabajará en su proyecto educativo" con seis opciones (transversal o integral; por recurso o área; por grados; por recursos o áreas afines; Mixta; Otros). Con **Mixta u Otros** aparece la **Descripción** (20 a 100 palabras, placeholder del dueño); con las otras cuatro no aparece ni se guarda.
2. **Justificación de la composición de la(s) academia(s) docente(s)**: 100 a 250 palabras, con la etiqueta y el placeholder del dueño.

"Siguiente" valida solo el bloque actual y no escribe; "Anterior" no valida ni pierde lo tecleado. "Guardar" (bloque 2) valida todo; si falla, no escribe nada y muestra el primer bloque con error. Aviso: "Sección completa". El paso `proyecto_educativo` no se marca completado desde aquí. Cambiar de Mixta/Otros a otra opción **borra** la descripción guardada.

## Base de datos

`2026_11_12_000800_create_proyecto_educativo_academias_table.php`: una fila por nivel (`pe_academias_nivel_unico`), `tipo_academia` (CHECK `pe_academias_tipo_valido` con las seis claves), `descripcion_academia` (CHECK `pe_academias_descripcion_coherente`: obligatoria y no vacía con `mixta`/`otros`, NULL con las demás), `justificacion_academia` (no vacía). Tipo y justificación son NOT NULL porque la fila se crea solo con el guardado completo. El rango de palabras lo valida `ReglasAcademiasProyecto`, no la base. Migración corrida en desarrollo (autorización permanente del dueño).

## Estructura

Nuevos: `ProyectoEducativoAcademias` (modelo), `DatosAcademiasProyecto`, `ReglasAcademiasProyecto` (TIPOS, REQUIEREN_DESCRIPCION, rangos, BLOQUES), `GuardarAcademiasProyecto`, `AcademiasProyectoCapturada`, `SeccionesProyectoEducativo::academias()` (índice 11). `EstadoSeccionesProyecto` (sección 12 completa cuando la fila cumple las reglas: índice y menú lateral). En `ProyectoEducativoSeccion`: propiedades `tipo_academia`, `descripcion_academia`, `justificacion_academia`, `siguienteAcademias`/`anteriorAcademias`, y la sección entra en la lista de secciones de texto y en `irAlPrimerBloqueConErrores`. El radio usa `wire:model.live` para mostrar la descripción al elegir.

## Supuestos míos (el dueño no dio límites)

- Descripción 20–100 palabras y Justificación 100–250 palabras. Cambiar un rango es un cambio de una línea en `ReglasAcademiasProyecto`.
- Nombres de campo con sufijo `_academia` para no chocar con las propiedades de otras secciones en el mismo componente.

## Evidencia

- Pruebas escritas antes del código y vistas fallar (28 pruebas, 26 en rojo por clases inexistentes): `GuardarAcademiasProyectoTest` (16, incluye CHECKs de la base) y `Paso3AcademiasProyectoTest` (12).
- Dos fallos propios de las pruebas, corregidos en las pruebas: el título del bloque 2 ya aparece en las pestañas del bloque 1, y `bloque` es `#[Locked]` (se llega al bloque 2 con `siguienteAcademias`).
- Los cuatro marcadores "Contenido por definir" (`Paso3Filosofia/Objetivos/Presentacion/ProyectoEducativoTest`) ahora apuntan a la sección 13 (índice 12, 8 restantes, "Criterios de Acreditación, Promoción y Certificación").
- Pint limpio, PHPStan 0. La corrida enfocada de regresión se reporta en el mensaje de cierre.

## Qué NO se hizo / límites

- **No se vio en navegador**: aparición de la descripción al elegir Mixta/Otros, el conteo de palabras y el paso entre bloques.
- La suite completa no se corrió localmente (regla del dueño); la verifica CI al subir.
- `ResumenEliminacionNivel` no cuenta las filas de texto de las secciones 1 a 12 (se borran en cascada sin listarse).
- `docs/progress.md` no se tocó.
