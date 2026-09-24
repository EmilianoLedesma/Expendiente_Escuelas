# PENDIENTE — Matriz de aplicabilidad de tipos de espacios por nivel educativo

**Estado:** abierto
**Abierto:** 2026-09-21
**Requiere:** confirmación normativa de SEDEQ
**Bloquea:** nada hoy. `TiposEspaciosSeeder` siembra una matriz utilizable; esta
pregunta afecta su exactitud, no su existencia.

## Pregunta

¿A qué niveles de Educación Básica aplica cada tipo de espacio, y cuáles son
obligatorios?

## Lo que COMPENDIO sí afirma explícitamente

- `filtro_recepcion` → solo Inicial ("Inicial incluye Filtro/Recepción, que el genérico no tiene").
- `subdireccion`, `atencion_publico`, `bodega`, `sala_maestros` → catálogo genérico de Básica, no Inicial.
- `biblioteca` → solo Primaria y Secundaria.
- `taller`, `laboratorio_polifuncional`, `salon_usos_multiples`, `auditorio`, `cocina`, `comedor`, `sala_artes` → "instalaciones especiales exclusivas de Básica", no Inicial.
  **Superseded 2026-09-24 para `salon_usos_multiples`, `cocina`, `comedor`:** ver
  "Actualización — 2026-09-24" más abajo. Estos tres espacios sí aplican a
  Inicial; se deja esta línea sin borrar para no perder el razonamiento
  histórico, pero ya no es correcta para esos tres. Vigente para el resto
  (`taller`, `laboratorio_polifuncional`, `auditorio`, `sala_artes`).

Estas filas están sembradas exactamente así y no son objeto de esta pregunta.

## Filas inferidas (COMPENDIO guarda silencio)

Sembradas para los 4 niveles de Básica con `obligatorio = false`:

`direccion`, `oficinas_administrativas`, `control_escolar`, `cubiculo`,
`cancha_usos_multiples`, `chapoteadero`, `arenero`, `zona_juegos`,
`areas_verdes`, `area_recreo`, `campo_futbol`, `otra_recreativa`,
`otra_especial`.

Razón de la inferencia: mostrar un espacio capturable que no aplica es
recuperable (el solicitante lo deja vacío); ocultar uno que sí aplica
haría la captura imposible. El sesgo elegido es el recuperable.

## Obligatoriedad

`niveles_tipos_espacios.obligatorio` está sembrado en `false` para **todas**
las filas, incluidas las explícitas. COMPENDIO enumera espacios capturables
pero en ningún punto marca alguno como obligatorio; la columna existe en el
DDL para cuando SEDEQ confirme esa matriz. Ninguna regla de obligatoriedad
fue inventada.

## Qué cambiaría al resolverse

Solo datos de `TiposEspaciosSeeder` (constante `APLICABILIDAD_EXPLICITA` y
los `obligatorio`). Ningún cambio de esquema, ningún cambio de código de
captura: el formulario de sub-paso 2 se genera desde estas filas.

---

## Actualización — 2026-09-24: Inicial agregado a tres espacios ya explícitos

**Corrección 2026-09-24 (revisión):** la redacción original de esta sección
contenía tres imprecisiones, detectadas en revisión de evidencia. Se corrigen
aquí; el texto anterior no se borra en la sección de arriba, se anota como
superseded.

**Cambio real:** `salon_usos_multiples`, `cocina` y `comedor` **ya estaban**
en `TiposEspaciosSeeder::APLICABILIDAD_EXPLICITA` (sembrados explícitamente
para `preescolar`, `primaria`, `secundaria` — no en la sección "inferida").
El commit `f837f86` únicamente agregó `'inicial'` a la lista de niveles de
esas tres claves ya explícitas. No hubo movimiento de "inferidas" a
"explícitas": nunca estuvieron en la lista inferida. (Corrige la afirmación
anterior de esta sección, que sí decía eso — era falsa.)

**Evidencia (WS-3.1):**
- Requisitos Inicial §(ñ) exige Sala de usos múltiples para Inicial — citada
  aquí solo por su paráfrasis: el Apéndice B.3 del brief de remediación
  (`AGENT_BRIEF_remediacion-auditoria-2026-09-23.md`), que parafrasea
  Requisitos Inicial §ñ, dice: *"Sala de usos múltiples: 1.2 m² per child
  (lactantes B/C, maternales A/B)"*. No es una cita verbatim de Requisitos
  Inicial; el texto exacto de Requisitos Inicial no está en el repo.
- `reglas_validacion` en BD: `inicial.superficie.sala_usos_multiples`
  (1.2 m²/niño).
- `mobiliario_conceptos` (`database/seeders/MobiliarioConceptosSeeder.php`,
  ~líneas 92-94) siembra tres filas SUM con `sala_id = null`: **"Silla
  infantil con cinturón (lactantes)"**, **"Silla infantil (maternal)"** y
  **"Mesa infantil"**. No existe mobiliario de comedor/cocina en el catálogo
  para Inicial ni para ningún nivel — la afirmación anterior de que las tres
  filas incluían "'Silla para niño' y mobiliario de comedor" era falsa; se
  corrige aquí.

**Impacto:** la matriz ahora agrega Inicial a tres espacios que ya eran
explícitos para el resto de Básica. La pregunta principal de este documento
(obligatoriedad) sigue abierta.
