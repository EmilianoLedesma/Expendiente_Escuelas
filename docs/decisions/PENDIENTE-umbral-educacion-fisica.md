# PENDIENTE — Umbral de alumnos para Educación Física obligatoria (60 vs. 61)

**Estado: abierto.** No adjudicado por el agente. Requiere confirmación de SEDEQ.

## Por qué existe este documento

Un reporte previo (`docs/reports/2026-09-07-reglas-validacion-schema.md`, sección 2.1)
cerró esta pregunta como "verificado, no es un bug", tratando la redacción de la PRD
y la de COMPENDIO §5.2 como si fueran dos fuentes independientes que coinciden. Eso
fue incorrecto por dos razones: (1) la PRD dice explícitamente que COMPENDIO es la
fuente normativa autoritativa — la PRD no corrobora nada de forma independiente, solo
hereda lo que diga COMPENDIO; y (2) dentro del propio COMPENDIO hay más de una
redacción, y no todas dicen lo mismo. La tarea original pedía **reportar** esta
discrepancia para que el usuario la resuelva con SEDEQ, no resolverla. Este documento
reabre la pregunta correctamente.

## Todas las redacciones encontradas (verbatim, con ubicación)

| # | Texto exacto | Ubicación | Nivel(es) |
|---|---|---|---|
| 1 | "docente de Educación Física obligatorio **solo si la instalación tiene capacidad de 60 alumnos o más**" | `docs/COMPENDIO_MAESTRO_Sistema_Incorporacion.md`, §5.1, línea 452 | Preescolar |
| 2 | "docente de Educación Física obligatorio **por cada 60 alumnos o más en la escuela** (proporción, se puede requerir más de uno)" | §5.1, línea 453 | Primaria |
| 3 | "**Docente de Educación Física** (obligatorio si capacidad **≥60 alumnos**)" | §5.1, línea 473 (detalle de profesiograma) | Preescolar |
| 4 | "**Docente de Educación Física** (obligado **por cada 60 alumnos o más** en la escuela)" | §5.1, línea 480 (detalle de profesiograma) | Primaria |
| 5 | "Educación Física **obligatoria si el número de educandos es mayor a 60**" | §5.2, línea 540, dentro de la sección "### Preescolar (Acuerdo 357)" — prosa normativa, no tabla resumen | Preescolar |
| 6 | "Educación Física **obligatoria si >60 alumnos**" | §5.2, línea 575, dentro de "### Primaria (Acuerdo 254)" — prosa normativa, no tabla resumen | Primaria |
| 7 | "si hay **más de 60 alumnos**, es obligatorio contar no solo con profesor de Educación Física, sino también con un trabajador social y un prefecto" | §5.2, línea 626, dentro de "### Secundaria (Acuerdo 255)" | Secundaria |
| 8 | Fila de tabla: "Educación Física obligatoria \| **>60 alumnos** \| **>60 alumnos** \| **>60 alumnos** (+ trabajador social + prefecto)" | §5.2, línea 640, tabla "Comparativo rápido entre los tres niveles" | Preescolar/Primaria/Secundaria |
| 9 | PRD §5: "> 60" | `docs/PRD_Sistema_Incorporacion_MVP.md`, §5 | (no distingue nivel en el pasaje citado en la tarea original) |

**Corrección a una caracterización previa**: un reporte anterior de este mismo trabajo
afirmó que ">60" "aparece en una tabla resumen" mientras que "60 o más" era "prosa más
cercana a la fuente Profesiograma" — implicando que ">60" era menos central. Eso es
inexacto: ">60" aparece tanto en la tabla resumen (fila 8) **como** en la prosa
normativa de cada nivel dentro de §5.2 (filas 5, 6, 7), no solo en la tabla.

## De dónde viene cada redacción (trazado a la fuente que el propio COMPENDIO declara)

- **Filas 1–4** (§5.1, "60 o más" / "≥60"): la sección §5.1 declara su fuente en su
  propio encabezado (línea 447): *"Fuente: `PROFESIOGRAMA_INICIAL.pdf`,
  `PROFESIOGRAMA_PREESCOLAR.pdf`, `PROFESIOGRAMA_PRIMARIA.pdf`,
  `PROFESIOGRAMA_SECUNDARIA.pdf` (ciclo escolar 2023-2024)."* Es decir, estas
  redacciones derivan de los **Profesiogramas** (documentos de perfil de personal),
  no directamente del texto del Acuerdo Secretarial.
- **Filas 5–8** (§5.2, ">60"/"mayor a 60"): la sección §5.2 declara su fuente en su
  propio encabezado (línea 514): *"se obtuvo el texto completo de los tres Acuerdos
  Secretariales directamente de fuentes oficiales de la SEP (Acuerdo 357 vía
  `portal.sepyc.gob.mx`, Acuerdo 254 vía `normatecainterna.sep.gob.mx`, Acuerdo 255 vía
  `evaluacion.septlaxcala.gob.mx`)."* Es decir, estas redacciones derivan directamente
  de los **Acuerdos Secretariales 357/254/255** — el texto legal primario.
- **Fila 9** (PRD): la PRD no cita una fuente propia para este dato específico. Dado
  que la PRD subordina su propio contenido normativo a COMPENDIO (ver el preámbulo de
  la PRD), la fila 9 no es una fuente independiente — es, en el mejor de los casos,
  una repetición de alguna de las anteriores, probablemente de §5.2 dado que coincide
  en la redacción exacta (">60").

## Sobre la "decisión del 2026-09-04"

El seeder anterior a este trabajo, y `docs/progress.md`, citan una decisión previa.
Cita textual, con ubicación:

> "**Educación Física / trabajador social / prefecto staffing threshold set to 'más
> de 60' (61), not '≥60' (60)**: COMPENDIO_MAESTRO is internally inconsistent — §5.1
> says '≥60 alumnos'/'60 o más', §5.2 (the Acuerdos Secretariales text) says 'más de
> 60'. User confirmed 61 explicitly (2026-09-04) when raised during code review;
> §5.2's Acuerdos text is the more authoritative/later source."
>
> — `docs/progress.md`, sección "Decisions Log" (entrada fechada 2026-09-04)

Esta cita es real y verificable en el archivo — no se retracta. Sí se retracta la
conclusión de que esa decisión **cierra** la pregunta de forma permanente: fue una
confirmación puntual del usuario durante una revisión de código anterior, no una
resolución formal con SEDEQ, y el usuario ha pedido explícitamente reabrir la
pregunta ahora. Se mantiene aquí como uno de los insumos para la decisión final, no
como el veredicto.

## Consecuencia concreta para una escuela con exactamente 60 alumnos

| Lectura | ¿Requiere docente de Educación Física con 60 alumnos exactos? |
|---|---|
| "60 alumnos o más" / "≥60" (filas 1, 3) | **Sí** — 60 alcanza el umbral. |
| "más de 60" / ">60" (filas 5, 6, 7, 8, 9) | **No** — se necesitan 61 para activar el requisito. |

Ambas lecturas conviven dentro del mismo documento (COMPENDIO). La diferencia práctica
es un solo alumno: una escuela con capacidad de exactamente 60 alumnos pasa o no pasa
la validación según cuál lectura se implemente.

## Pregunta exacta para SEDEQ (formulada para una persona no técnica)

> "Para que una escuela de nivel Preescolar, Primaria o Secundaria esté obligada a
> tener un maestro de Educación Física, ¿el número de alumnos inscritos debe ser
> **de 60 en adelante** (una escuela con exactamente 60 alumnos ya lo requiere), o
> debe ser **mayor a 60, es decir 61 o más** (una escuela con exactamente 60 alumnos
> todavía NO lo requiere)? Los documentos internos que tenemos tienen ambas
> redacciones y necesitamos que SEDEQ confirme cuál aplica."

## Valor sembrado mientras tanto (PROVISIONAL)

`condicion_min = 61` en las cuatro reglas `*_umbral` (`preescolar.personal.
educacion_fisica`, `secundaria.personal.educacion_fisica`,
`secundaria.personal.trabajador_social`, `secundaria.personal.prefecto`) —
sin cambios respecto al valor ya sembrado. Marcado explícitamente como
**PROVISIONAL** en el docblock de `ReglasValidacionSeeder` y en este documento.
No se debe cambiar sin resolver esta pregunta con SEDEQ; tampoco se debe tratar
como definitivo solo porque ya está sembrado.

No aplica a `primaria.personal.educacion_fisica`, cuyo umbral ahora es implícito
en `floor(alumnos / 60)` (ver `docs/reports/2026-09-07-reglas-validacion-schema.md`),
lo cual introduce una tercera variante práctica: bajo división entera, el umbral
efectivo es 60 (inclusive), no 61 — otro punto que esta misma pregunta a SEDEQ
debería resolver de forma consistente para los tres niveles.
