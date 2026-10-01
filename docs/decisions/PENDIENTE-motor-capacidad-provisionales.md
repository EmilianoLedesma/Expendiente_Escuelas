# PENDIENTE — Elecciones provisionales del Motor de Capacidad Instalada y del Paso 3

**Estado:** abierto. El agente tuvo que elegir para que el motor y los sub-pasos 4–6 funcionen; cada punto se puede revertir sin rediseño. Requiere confirmación del owner (P2–P10) y de SEDEQ (P1).
**Fecha:** 2026-09-30
**Origen:** `docs/reports/2026-09-30-motor-capacidad-instalada.md`
**Rama:** `feat/motor-capacidad-instalada` (mergeada a `master` en `df9ce33`, PR #2)

Documentos relacionados que siguen abiertos y que este archivo **no** resuelve: `PENDIENTE-origen-de-magnitud.md`, `PENDIENTE-umbral-educacion-fisica.md`, `PENDIENTE-personal-condicionado-por-grado.md`, `PENDIENTE-matriz-espacios-por-nivel.md`.

---

## P1. Umbrales de personal: ¿matrícula o capacidad instalada? (SEDEQ)

`PENDIENTE-umbral-educacion-fisica.md` señala que el Profesiograma habla de **capacidad** de las instalaciones y el Acuerdo de **alumnos**.

**Elección provisional:** matrícula capturada en el sub-paso Matrícula, que es el único número de alumnos que el sistema tiene. El umbral sigue en 61 (`condicion_min`, provisional en ese otro PENDIENTE).

## P2. Superficie del predio con varios niveles en el mismo plantel

`PENDIENTE-origen-de-magnitud.md` §3.

**Elección provisional:** las reglas `*.superficie.predio_total` usan la matrícula de **todos los niveles de todas las escuelas del plantel**. El predio lo usan todos sus alumnos. Los niveles sin matrícula capturada suman 0.

## P3. ¿La capacidad instalada bloquea el envío?

**Elección provisional:** no. El PRD dice "mostrar advertencias... **sin bloquear el guardado** (el expediente puede quedar 'con observaciones')". En la página y el PDF se muestran como **"Observación"**, con enlace al sub-paso donde se corrige. La validación documental (ADR-007) sigue siendo la única que bloquea.

## P4. Áreas compartidas entre niveles (recreativas, sala de usos múltiples, sanitarios, biblioteca)

Los espacios se capturan por plantel, no por nivel.

**Elección provisional:** la regla de cada nivel compara su propio requerimiento contra el área **total** del plantel. Con dos niveles en el plantel, cada uno puede cumplir por separado aunque la suma de sus requerimientos no quepa. Es la misma pregunta de P2, aplicada a los espacios, y se deja con la solución más simple.

## P5. Mobiliario de Inicial

- **Redondeo:** "1 por cada N niños" redondea **hacia arriba** por sala. La norma no lo dice; hacia abajo dejaría niños sin el artículo.
- **Conceptos de la sala de usos múltiples** (`sala_id` nulo): **no se evalúan**, porque el catálogo no dice si el ratio cuenta lactantes, maternales o todos. Se listan como "no verificados".

## P6. Acervo bibliográfico

**Elección provisional:** se cuentan solo los títulos de tipo `libros` de las bibliotecas del plantel ("acervo bibliográfico"). Revistas, videos, etc. no cuentan.

## P7. Plan de estudios y modalidad

- **Turno y tipo de alumnado** ya no se capturan aquí: desde WS-5b (en `master`) son de Paso 2.4, donde cambiarlos descarta el Formato de Solicitud (decisión del dueño). Plan de estudios los muestra solo como referencia y nunca los escribe.

- **Plataforma educativa:** obligatoria fuera de la modalidad escolarizada; se descarta en la escolarizada.
- **"Plan de estudios":** referencia libre y opcional. COMPENDIO §7 sigue sin definir los campos exactos del plan.

## P8. Plantilla docente y matrícula

- **Guardado:** cada envío **reemplaza** la plantilla y la matrícula completas del nivel (el formulario siempre manda todo).
- **Plantilla:** exige al menos una persona, con los seis datos del Anexo 1. La sala es obligatoria para los cargos con `requiere_sala` (Inicial) y la asignatura para los que tienen `requiere_asignatura` (Docente Titular de Secundaria).
- **Matrícula:** exige al menos un alumno. Inicial se captura por sala; los demás niveles, por grado y grupo.
- **Grados:** se sembraron `grados` (Preescolar 3, Primaria 6, Secundaria 3), que nunca se habían sembrado.
- **Asistentes de Inicial:** solo cuentan para lactantes o maternales si están **asignados a una sala** de ese tipo.

## P9. "Revisar la captura" hacia Datos del inmueble

Las observaciones de predio y superficie construida enlazan a "Datos del inmueble". Esa página, una vez completa, sigue siendo de solo lectura y redirige hacia adelante (WS-7, `PENDIENTE-edicion-hasta-envio.md`). El enlace no permite corregir hasta que WS-7 abra la edición. Infraestructura, Mobiliario, Plan de estudios, Plantilla y Matrícula sí se pueden reabrir y guardar de nuevo.

## P10. Educación Física en Secundaria

`PENDIENTE-perfiles-trabajador-social-prefecto.md`, "Hueco relacionado": la regla no tiene `cargo_puesto_id`.

**Elección provisional:** la opción (b) de ese documento. El motor cuenta a los Docentes Titulares cuya asignatura es "Educación Física" (`personal_asignaturas`), sin cambio de esquema.

**Fragilidad (revisión 2026-10-01):** la coincidencia es por el **nombre** de la asignatura (`ConstruirDatosCapacidad::ASIGNATURA_EDUCACION_FISICA = 'Educación Física'`, comparado contra `asignaturas.nombre`), no por una clave estable; si el catálogo `AsignaturasSeeder` cambia la redacción o la acentuación, el conteo cae a 0 sin ningún error.

## Efecto secundario de P2 en planteles compartidos (revisión 2026-10-01)

`ConstruirDatosCapacidad::matriculaPlantel()` suma la matrícula (`matricula_grados` + `matricula_salas`) de todos los `escuela_niveles` de **todas las escuelas del plantel**, sin filtrar por solicitante. Esa suma es la magnitud de las reglas `*.superficie.predio_total`, y el "Requerido: X m²" que se muestra en la página de validación final y en el PDF se calcula a partir de ella. Si un plantel tuviera escuelas de dos solicitantes distintos, cada uno podría deducir la matrícula declarada por el otro (requerido ÷ m² por alumno − su propia matrícula).

Hoy esto solo puede ocurrir con planteles compartidos heredados: desde el 2026-09-23 `IniciarTramiteNuevo` rechaza adjuntar una escuela a un plantel sin escuelas propias, pero la consulta de esa fecha halló un plantel de desarrollo con escuelas de más de un solicitante. Depende, por tanto, de `PENDIENTE-plantel-solicitante-cardinalidad.md`: si se confirma 1 plantel : 1 solicitante (y se limpia el caso heredado), el efecto desaparece; si se permite compartir planteles, P2 debe decidir si la suma incluye a otros dueños y cómo se muestra sin revelar su matrícula. No se cambió el comportamiento.
